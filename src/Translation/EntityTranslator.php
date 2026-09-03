<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Proxy;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Event\PostTranslateEvent;
use Tmi\TranslationBundle\Event\PreTranslateEvent;
use Tmi\TranslationBundle\Translation\Cache\TranslationCacheInterface;
use Tmi\TranslationBundle\Translation\Context\EntityTranslationContext;
use Tmi\TranslationBundle\Translation\Context\TranslationContext;
use Tmi\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;

final class EntityTranslator implements EntityTranslatorInterface, ResetInterface
{
    /**
     * Handlers ordered by descending priority; first match wins.
     *
     * @var list<array{priority: int, handler: TranslationHandlerInterface}>
     */
    private array $handlers = [];

    /**
     * (tuuid, locale) pairs a preload() batch query already looked up and found
     * nothing for -- see the preload() docblock. Cleared per pair the moment
     * runHandlers() caches a translation for it, and wholesale by reset().
     *
     * @var array<string, array<string, true>>
     */
    private array $knownMisses = [];

    private LoggerInterface|null $logger = null;

    /**
     * @param array<string> $locales
     */
    public function __construct(
        #[Autowire(param: 'tmi_translation.default_locale')]
        private readonly string $defaultLocale,
        private readonly array $locales,
        private readonly bool $copySource,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AttributeHelper $attributeHelper,
        private readonly TypeDefaultResolver $typeDefaultResolver,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslationCacheInterface $cache,
        private readonly LocaleVariantFinder $finder,
        LoggerInterface|null $logger = null,
    ) {
        $this->logger = $logger;
    }

    public function setLogger(LoggerInterface|null $logger): void
    {
        $this->logger = $logger;
    }

    public function translate(TranslatableInterface $entity, string $locale): TranslatableInterface
    {
        $result = $this->processTranslation(new EntityTranslationContext($entity, $entity->getLocale(), $locale));
        \assert($result instanceof TranslatableInterface);

        return $result;
    }

    public function translateAndPersist(TranslatableInterface $entity, string $locale): TranslatableInterface
    {
        $result = $this->translate($entity, $locale);
        $this->entityManager->persist($result);

        return $result;
    }

    public function getOrTranslate(TranslatableInterface $entity, string $locale): TranslatableInterface
    {
        $result = $this->translate($entity, $locale);

        if (!$this->entityManager->contains($result)) {
            $this->entityManager->persist($result);
        }

        return $result;
    }

    /**
     * Batch-loads each entity's $locale variant into the cache ahead of time,
     * one query per class rather than one per entity.
     *
     * translate() already warms its own cache entry before running the
     * handler chain, so a loop calling translate() one entity at a time
     * costs one lookup query per entity regardless. Calling preload() with
     * the whole batch first turns that into one lookup query per class --
     * translate() then finds every entry already cached and skips its own
     * lookup. Entities without a $locale variant are simply not found and
     * are translated (created) normally when translate() reaches them.
     *
     * Goes through the finder rather than a plain query builder: a locale-filtered
     * lookup here would only ever see the current request's locale, never find an
     * existing translation in $locale, and mint a duplicate row on every warmup.
     *
     * A cache hit is checked against the entity's real UnitOfWork state, the same
     * rule processTranslation() applies at its own cache-hit sites: a hit surviving
     * an EntityManager::clear() still sits in the cache holding an identifier the
     * UnitOfWork no longer tracks, and get() alone cannot tell that apart from a
     * live one. Treating a detached hit as reusable here would suppress the query
     * that reloads (or reuses) a managed instance, and processTranslation()'s own
     * post-preload check would then hand the caller a detached instance whose
     * later persist() re-inserts it as a duplicate row instead of finding it.
     *
     * A (tuuid, locale) pair a batched lookup query below already looked up and
     * found nothing for is remembered ($knownMisses) so a later preload() call --
     * including processTranslation()'s own single-entity one, which is what makes
     * an import's per-entity translate() loop not re-query a Tuuid this same
     * preload() call already ruled out -- skips the query for it instead of asking
     * the database the same question again. The memory entry for a pair is dropped
     * the instant runHandlers() caches a translation for it, so a variant this
     * translator itself creates is always found again, even across an
     * EntityManager::clear(). The one gap this leaves: a variant for a remembered
     * pair created by some other means -- a manual persist(), another process --
     * stays invisible to preload() until this translator creates one for that pair
     * or the service is reset (see reset(), tagged kernel.reset in services.yaml).
     *
     * @param iterable<mixed> $entities
     */
    public function preload(iterable $entities, string $locale): void
    {
        /** @var array<class-string, list<string>> $byClass */
        $byClass = [];

        foreach ($entities as $entity) {
            if (!$entity instanceof TranslatableInterface) {
                continue;
            }
            $tuuid = $entity->getTuuid()->getValue();

            $cached = $this->cache->get($tuuid, $locale);
            if (null !== $cached && !$this->isDetachedCacheHit($cached)) {
                continue;
            }

            if (isset($this->knownMisses[$tuuid][$locale])) {
                continue;
            }

            $byClass[$entity::class][] = $tuuid;
        }

        foreach ($byClass as $class => $tuuids) {
            /** @var array<string, true> $found */
            $found = [];

            foreach ($this->finder->findLocaleVariantsBatch($class, $tuuids, $locale) as $translation) {
                $translationTuuid         = $translation->getTuuid()->getValue();
                $found[$translationTuuid] = true;

                $this->cache->set($translationTuuid, $translation->getLocale() ?? $locale, $translation);
                unset($this->knownMisses[$translationTuuid][$locale]);
            }

            foreach ($tuuids as $tuuid) {
                if (!isset($found[$tuuid])) {
                    $this->knownMisses[$tuuid][$locale] = true;
                }
            }
        }
    }

    /**
     * Forgets every (tuuid, locale) pair preload() has recorded as a miss -- see
     * the preload() docblock. services.yaml tags this service kernel.reset
     * explicitly (Symfony does not autoconfigure ResetInterface into that tag), so
     * a long-running worker starts each unit of work without a previous one's miss
     * memory: a variant created between them by some means other than this
     * translator becomes visible again immediately, matching
     * InMemoryTranslationCache's own kernel.reset behaviour.
     */
    public function reset(): void
    {
        $this->knownMisses = [];
    }

    /**
     * Process translation for a given entity or property.
     *
     * This method handles:
     *  - Top-level entity translation
     *  - Properties with #[SharedAmongstTranslations] or #[EmptyOnTranslate]
     *  - Embedded properties that may contain shared or empty attributes internally
     *
     * @param TranslationContext $context contains the entity or property to translate, source/target locales, and parent entity
     *
     * @return mixed Translated entity, embedded, or property value according to attribute rules
     */
    public function processTranslation(TranslationContext $context): mixed
    {
        $subject = $context->getSubject();
        $locale  = $context->getTargetLocale() ?? $this->defaultLocale;

        // Validate that the requested locale is allowed
        if (!in_array($locale, $this->locales, true)) {
            throw new \LogicException(sprintf('Locale "%s" is not allowed. Allowed locales: %s', $locale, implode(', ', $this->locales)));
        }

        // Handle top-level entities that implement TranslatableInterface
        if ($subject instanceof TranslatableInterface) {
            // Translating an entity into the locale it already carries is the identity
            // operation. Cloning it would be wrong on its own, and caching that clone under
            // (tuuid, locale) would hand it back to every later translate() for this pair --
            // which is exactly what translate($entity, $entity->getLocale()) asks for, a call
            // shape consumers are free to make (a no-op by construction). The info log below
            // sits after this check for the same reason: an identity call did no translation
            // work and should not be reported as if it had.
            if ($subject->getLocale() === $locale) {
                return $subject;
            }

            $this->logInfo('Starting translation of {class}', [
                'class'         => $subject::class,
                'source_locale' => $subject->getLocale(),
                'target_locale' => $locale,
            ]);

            $tuuidValue = $subject->getTuuid()->getValue();

            // Return a cached translation immediately when available -- but only while
            // the EntityManager still recognizes it. A hit surviving an EntityManager::
            // clear() (import batches, long-running workers) still sits in the cache
            // holding an identifier, yet the UnitOfWork no longer tracks it: without
            // $assume, getEntityState() falls through to the identifier check and
            // reports STATE_DETACHED. Handing that instance back would make
            // getOrTranslate() persist() it -- and persist() assumes STATE_NEW for
            // anything the UnitOfWork does not track, re-inserting the detached
            // instance as a brand-new row instead of reusing the existing one. A stale
            // hit is therefore treated as a miss and falls through to the regular
            // lookup, which reloads (or reuses) a managed instance and overwrites the
            // cache entry.
            $cached = $this->cache->get($tuuidValue, $locale);
            if (null !== $cached && !$this->isDetachedCacheHit($cached)) {
                return $cached;
            }

            // Detect cycles to avoid infinite recursion
            if ($this->cache->isInProgress($tuuidValue, $locale)) {
                return $subject;
            }

            // Resolve copySource per entity (entity-level override or global config)
            if (null === $context->getCopySource()) {
                $context->setCopySource($this->resolveCopySource($subject));
            }

            // Mark as in-progress with auto-cleanup guarantee.
            // The flag stays set for the whole frame -- warmup, handler chain and any
            // recursion below it -- because that is what makes the cycle check above work.
            // The finally clears it exactly once, on completion OR failure of this frame,
            // so a throwing handler can never leave a stale flag behind that would make
            // every later translate() silently return the untranslated entity.
            $this->cache->markInProgress($tuuidValue, $locale);
            try {
                $this->preload([$subject], $locale);

                // Same identity check as above: preload() may have refreshed
                // the entry, but a still-detached hit (its tuuid was skipped because the
                // cache already held something for it) is still a miss here.
                $cached = $this->cache->get($tuuidValue, $locale);
                if (null !== $cached && !$this->isDetachedCacheHit($cached)) {
                    return $cached;
                }

                return $this->runHandlers($context, $locale);
            } finally {
                $this->cache->unmarkInProgress($tuuidValue, $locale);
            }
        }

        return $this->runHandlers($context, $locale);
    }

    /**
     * Registers a handler in the first-match-wins chain.
     *
     * Higher priority runs earlier. Handlers registered without a priority default to 0
     * and therefore keep registration order among themselves.
     */
    public function addTranslationHandler(TranslationHandlerInterface $handler, int|null $priority = null): void
    {
        $this->handlers[] = ['priority' => $priority ?? 0, 'handler' => $handler];

        // PHP sorts are stable, so equal priorities keep their registration order and two
        // handlers sharing a priority cannot displace each other.
        usort($this->handlers, static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);
    }

    /**
     * Runs the handler chain for the given context, first match wins.
     *
     * @return mixed the handler result, or the untouched data when no handler supports it
     */
    private function runHandlers(TranslationContext $context, string $locale): mixed
    {
        $subject = $context->getSubject();

        foreach ($this->handlers as ['handler' => $handler]) {
            if (!$handler->supports($context)) {
                continue;
            }

            // Handle attribute logic if a specific property is set on the context
            $property = $context->getProperty();

            $this->logDebug('Handler selected for processing', [
                'handler'   => $handler::class,
                'property'  => $property?->name,
                'data_type' => is_object($subject) ? $subject::class : gettype($subject),
            ]);

            // Dispatch PreTranslateEvent for top-level entities. Passing no event
            // name dispatches it under its own class, which is what listeners
            // registered on PreTranslateEvent::class (or #[AsEventListener]) expect.
            if ($subject instanceof TranslatableInterface) {
                $this->eventDispatcher->dispatch(new PreTranslateEvent($subject, $locale));
            }

            if ($property instanceof \ReflectionProperty) {
                // Validate property attributes for conflicts
                $this->attributeHelper->validateProperty($property, $this->logger);

                // 1. Determine if the top-level property is Shared (always copies from source)
                if ($this->attributeHelper->isSharedAmongstTranslations($property)) {
                    $this->logDebug('Attribute detected: SharedAmongstTranslations', [
                        'property' => $property->name,
                        'class'    => $property->class,
                        'action'   => 'sharing value across translations',
                    ]);

                    return $handler->translate($context->setShared(true));
                }

                // 2. Handle copy_source: false -- type-safe defaults for all non-shared fields
                if (false === $context->getCopySource()) {
                    // Embedded properties: delegate to handler for per-property resolution
                    if ($this->attributeHelper->isEmbedded($property)) {
                        if ($this->attributeHelper->isEmptyOnTranslate($property)) {
                            $this->logDebug('EmptyOnTranslate has no effect when copy_source is false', [
                                'property' => $property->name,
                                'class'    => $property->class,
                            ]);
                        }

                        return $handler->translate($context);
                    }

                    // Log redundancy hint if EmptyOnTranslate is present
                    if ($this->attributeHelper->isEmptyOnTranslate($property)) {
                        $this->logDebug('EmptyOnTranslate has no effect when copy_source is false', [
                            'property' => $property->name,
                            'class'    => $property->class,
                        ]);
                    }

                    // Non-nullable object safety fallback: copy from source
                    $type = $property->getType();
                    if ($type instanceof \ReflectionNamedType && !$type->isBuiltin() && !$type->allowsNull()) {
                        $this->logDebug(\sprintf(
                            'Property %s::$%s is non-nullable object -- copying from source despite copy_source: false',
                            $property->class,
                            $property->name,
                        ), []);

                        return $handler->translate($context);
                    }

                    // Resolve type-safe default
                    $default = $this->typeDefaultResolver->resolve($property);

                    $this->logDebug('Type-safe default for copy_source: false', [
                        'property' => $property->name,
                        'class'    => $property->class,
                    ]);

                    return $default;
                }

                // 3. Handle EmptyOnTranslate (copy_source: true path)
                if ($this->attributeHelper->isEmptyOnTranslate($property)) {
                    // A collection is emptied by handing back a fresh empty one, which is
                    // what its handler does. There is no type-safe default to resolve for it,
                    // and asking for one would fail as "non-nullable object".
                    if (!$subject instanceof Collection && !$this->attributeHelper->isNullable($property)) {
                        // Type-safe default instead of throwing
                        $default = $this->typeDefaultResolver->resolve($property);

                        $this->logDebug('Type-safe default for non-nullable EmptyOnTranslate property', [
                            'property' => $property->name,
                            'class'    => $property->class,
                            'default'  => $default,
                        ]);

                        return $default;
                    }

                    $this->logDebug('Attribute detected: EmptyOnTranslate', [
                        'property' => $property->name,
                        'class'    => $property->class,
                        'action'   => 'clearing value for translation',
                    ]);

                    return $handler->translate($context->setEmpty(true));
                }

                // Handle embeddable with unified per-property resolution
                if ($this->attributeHelper->isEmbedded($property)) {
                    $this->logDebug('Processing embedded property with per-property resolution', [
                        'property' => $property->name,
                        'class'    => $property->class,
                    ]);

                    return $handler->translate($context);
                }
            }

            $translated = $handler->translate($context);

            if ($subject instanceof TranslatableInterface && $translated instanceof TranslatableInterface) {
                $this->eventDispatcher->dispatch(new PostTranslateEvent($subject, $locale, $translated));

                $translatedTuuid  = $translated->getTuuid()->getValue();
                $translatedLocale = $translated->getLocale() ?? $locale;
                $this->cache->set($translatedTuuid, $translatedLocale, $translated);
                // A translation just created for this pair makes any earlier preload()
                // miss recorded for it stale -- see the preload() docblock.
                unset($this->knownMisses[$translatedTuuid][$translatedLocale]);

                $this->logDebug('Translation complete', [
                    'class'         => $translated::class,
                    'target_locale' => $translated->getLocale(),
                ]);
            }

            return $translated;
        }

        return $subject;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logDebug(string $message, array $context = []): void
    {
        if (null === $this->logger) {
            return;
        }
        $this->logger->debug('[TMI Translation] '.$message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logInfo(string $message, array $context = []): void
    {
        if (null === $this->logger) {
            return;
        }
        $this->logger->info('[TMI Translation] '.$message, $context);
    }

    /**
     * A cache hit is stale once the EntityManager itself no longer recognizes it.
     *
     * Called without $assume, getEntityState() only ever reports STATE_DETACHED for an
     * entity that carries an identifier but is absent from the UnitOfWork's own
     * bookkeeping -- exactly what EntityManager::clear() produces for a previously
     * managed instance still sitting in this cache. A freshly cloned, not-yet-flushed
     * translation has no identifier yet and reports STATE_NEW instead, and a
     * persisted-but-unflushed one is already recorded as STATE_MANAGED -- both remain
     * valid hits.
     */
    private function isDetachedCacheHit(TranslatableInterface $cached): bool
    {
        return UnitOfWork::STATE_DETACHED === $this->entityManager->getUnitOfWork()->getEntityState($cached);
    }

    /**
     * Resolves copySource for an entity: per-entity attribute overrides global config.
     *
     * A lazily-loaded association can arrive here as a classic Doctrine proxy
     * subclass -- reflecting it directly finds nothing, because PHP attributes are
     * never inherited by a generated subclass, and #[Translatable] lives on the
     * real class. Same proxy-unwrapping pattern DoctrineObjectHandler::supports()
     * already uses for the same reason.
     */
    private function resolveCopySource(object $entity): bool
    {
        $parentClass = $entity instanceof Proxy ? get_parent_class($entity) : false;
        $className   = \is_string($parentClass) ? $parentClass : $entity::class;

        $attribute = $this->attributeHelper->getTranslatableAttribute(new \ReflectionClass($className));
        if (null !== $attribute && null !== $attribute->copySource) {
            return $attribute->copySource;
        }

        return $this->copySource;
    }
}
