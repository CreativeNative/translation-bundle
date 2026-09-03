<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Event\TranslateEvent;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Cache\TranslationCacheInterface;
use Tmi\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;

final class EntityTranslator implements EntityTranslatorInterface
{
    /**
     * Handlers ordered by descending priority; first match wins.
     *
     * @var list<array{priority: int, handler: TranslationHandlerInterface}>
     */
    private array $handlers = [];

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
        $this->logInfo('Starting translation of {class}', [
            'class'         => $entity::class,
            'source_locale' => $entity->getLocale(),
            'target_locale' => $locale,
        ]);

        $result = $this->processTranslation(new TranslationArgs($entity, $entity->getLocale(), $locale));
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
     * Process translation for a given entity or property.
     *
     * This method handles:
     *  - Top-level entity translation
     *  - Properties with #[SharedAmongstTranslations] or #[EmptyOnTranslate]
     *  - Embedded properties that may contain shared or empty attributes internally
     *
     * @param TranslationArgs $args contains the entity or property to translate, source/target locales, and parent entity
     *
     * @return mixed Translated entity, embedded, or property value according to attribute rules
     */
    public function processTranslation(TranslationArgs $args): mixed
    {
        $entity = $args->getDataToBeTranslated();
        $locale = $args->getTargetLocale() ?? $this->defaultLocale;

        // Validate that the requested locale is allowed
        if (!in_array($locale, $this->locales, true)) {
            throw new \LogicException(sprintf('Locale "%s" is not allowed. Allowed locales: %s', $locale, implode(', ', $this->locales)));
        }

        // Handle top-level entities that implement TranslatableInterface
        if ($entity instanceof TranslatableInterface) {
            // Translating an entity into the locale it already carries is the identity
            // operation. Cloning it would be wrong on its own, and caching that clone under
            // (tuuid, locale) would hand it back to every later translate() for this pair --
            // which is exactly what the persist/update hooks ask for on every flush.
            if ($entity->getLocale() === $locale) {
                return $entity;
            }

            $tuuidValue = $entity->getTuuid()->getValue();

            // Return a cached translation immediately when available. get() doubles as
            // the existence check: on a PSR-6 pool, has() can report a hit whose entry
            // no longer loads (row deleted after caching, pre-3.2 entry format), and
            // a check-then-get would hand that null to translate(), where it escapes
            // as a TypeError once zend.assertions=-1 compiles the assert away. A hit
            // that cannot be loaded is a miss.
            $cached = $this->cache->get($tuuidValue, $locale);
            if (null !== $cached) {
                return $cached;
            }

            // Detect cycles to avoid infinite recursion
            if ($this->cache->isInProgress($tuuidValue, $locale)) {
                return $entity;
            }

            // Resolve copySource per entity (entity-level override or global config)
            if (null === $args->getCopySource()) {
                $args->setCopySource($this->resolveCopySource($entity));
            }

            // Mark as in-progress with auto-cleanup guarantee.
            // The flag stays set for the whole frame -- warmup, handler chain and any
            // recursion below it -- because that is what makes the cycle check above work.
            // The finally clears it exactly once, on completion OR failure of this frame,
            // so a throwing handler can never leave a stale flag behind that would make
            // every later translate() silently return the untranslated entity.
            $this->cache->markInProgress($tuuidValue, $locale);
            try {
                $this->warmupTranslations([$entity], $locale);

                // Single-call pattern as above: a "hit" that cannot be loaded is a miss.
                $cached = $this->cache->get($tuuidValue, $locale);
                if (null !== $cached) {
                    return $cached;
                }

                return $this->runHandlers($args, $entity, $locale);
            } finally {
                $this->cache->unmarkInProgress($tuuidValue, $locale);
            }
        }

        return $this->runHandlers($args, $entity, $locale);
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

    // --- EntityTranslatorInterface Hooks ---

    public function afterLoad(TranslatableInterface $entity): void
    {
        $this->translate($entity, $entity->getLocale() ?? $this->defaultLocale);
    }

    public function beforePersist(TranslatableInterface $entity): void
    {
        $this->translate($entity, $entity->getLocale() ?? $this->defaultLocale);
    }

    public function beforeUpdate(TranslatableInterface $entity): void
    {
        $this->translate($entity, $entity->getLocale() ?? $this->defaultLocale);
    }

    public function beforeRemove(TranslatableInterface $entity): void
    {
        $this->translate($entity, $entity->getLocale() ?? $this->defaultLocale);
    }

    /**
     * Runs the handler chain for the given args, first match wins.
     *
     * @return mixed the handler result, or the untouched data when no handler supports it
     */
    private function runHandlers(TranslationArgs $args, mixed $entity, string $locale): mixed
    {
        foreach ($this->handlers as ['handler' => $handler]) {
            if (!$handler->supports($args)) {
                continue;
            }

            // Handle attribute logic if a specific property is set in TranslationArgs
            $property = $args->getProperty();

            $this->logDebug('Handler selected for processing', [
                'handler'   => $handler::class,
                'property'  => $property?->name,
                'data_type' => is_object($entity) ? $entity::class : gettype($entity),
            ]);

            // Dispatch PRE_TRANSLATE event for top-level entities
            if ($entity instanceof TranslatableInterface) {
                $this->eventDispatcher->dispatch(
                    new TranslateEvent($entity, $locale),
                    TranslateEvent::PRE_TRANSLATE,
                );
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

                    return $handler->handleSharedAmongstTranslations($args);
                }

                // 2. Handle copy_source: false -- type-safe defaults for all non-shared fields
                if (false === $args->getCopySource()) {
                    // Embedded properties: delegate to handler for per-property resolution
                    if ($this->attributeHelper->isEmbedded($property)) {
                        if ($this->attributeHelper->isEmptyOnTranslate($property)) {
                            $this->logDebug('EmptyOnTranslate has no effect when copy_source is false', [
                                'property' => $property->name,
                                'class'    => $property->class,
                            ]);
                        }

                        return $handler->translate($args);
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

                        return $handler->translate($args);
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
                    if (!$entity instanceof Collection && !$this->attributeHelper->isNullable($property)) {
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

                    return $handler->handleEmptyOnTranslate($args);
                }

                // Handle embeddable with unified per-property resolution
                if ($this->attributeHelper->isEmbedded($property)) {
                    $this->logDebug('Processing embedded property with per-property resolution', [
                        'property' => $property->name,
                        'class'    => $property->class,
                    ]);

                    return $handler->translate($args);
                }
            }

            $translated = $handler->translate($args);

            if ($entity instanceof TranslatableInterface && $translated instanceof TranslatableInterface) {
                $this->eventDispatcher->dispatch(
                    new TranslateEvent($entity, $locale, $translated),
                    TranslateEvent::POST_TRANSLATE,
                );

                $this->cache->set($translated->getTuuid()->getValue(), $translated->getLocale() ?? $locale, $translated);

                $this->logDebug('Translation complete', [
                    'class'         => $translated::class,
                    'target_locale' => $translated->getLocale(),
                ]);
            }

            return $translated;
        }

        return $entity;
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
     * Resolves copySource for an entity: per-entity attribute overrides global config.
     */
    private function resolveCopySource(object $entity): bool
    {
        $attribute = $this->attributeHelper->getTranslatableAttribute(new \ReflectionClass($entity));
        if (null !== $attribute && null !== $attribute->copySource) {
            return $attribute->copySource;
        }

        return $this->copySource;
    }

    /**
     * Batch-load translations for given entities and target locale.
     *
     * Goes through the finder rather than a plain query builder: a locale-filtered
     * lookup here would only ever see the current request's locale, never find an
     * existing translation in $locale, and mint a duplicate row on every warmup.
     *
     * @param array<mixed> $entities
     */
    private function warmupTranslations(array $entities, string $locale): void
    {
        /** @var array<class-string, list<string>> $byClass */
        $byClass = [];

        foreach ($entities as $entity) {
            if (!$entity instanceof TranslatableInterface) {
                continue;
            }
            $tuuid = $entity->getTuuid()->getValue();
            // get() rather than has(): a stale pool key whose entry no longer loads
            // must not suppress the warmup query for its tuuid.
            if (null !== $this->cache->get($tuuid, $locale)) {
                continue;
            }
            $byClass[$entity::class][] = $tuuid;
        }

        foreach ($byClass as $class => $tuuids) {
            foreach ($this->finder->findLocaleVariantsBatch($class, $tuuids, $locale) as $translation) {
                $this->cache->set(
                    $translation->getTuuid()->getValue(),
                    $translation->getLocale() ?? $locale,
                    $translation,
                );
            }
        }
    }
}
