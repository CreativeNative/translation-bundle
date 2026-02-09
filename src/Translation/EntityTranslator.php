<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Event\TranslateEvent;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Cache\TranslationCacheInterface;
use Tmi\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * ToDo: Improve #[EmptyOnTranslate] handling for non-nullable and scalar fields
 * See GitHub Issue: https://github.com/CreativeNative/translation-bundle/issues/2.
 */
final class EntityTranslator implements EntityTranslatorInterface
{
    /** @var array<TranslationHandlerInterface> */
    private array $handlers = [];

    private LoggerInterface|null $logger = null;

    /**
     * @param array<string> $locales
     */
    public function __construct(
        #[Autowire(param: 'tmi_translation.default_locale')]
        private readonly string $defaultLocale,
        private readonly array $locales,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AttributeHelper $attributeHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslationCacheInterface $cache,
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
            $tuuidValue = $entity->getTuuid()->getValue();

            // Return cached translation immediately if available
            if ($this->cache->has($tuuidValue, $locale)) {
                return $this->cache->get($tuuidValue, $locale);
            }

            // Detect cycles to avoid infinite recursion
            if ($this->cache->isInProgress($tuuidValue, $locale)) {
                return $entity;
            }

            // Mark as in-progress with auto-cleanup guarantee
            $this->cache->markInProgress($tuuidValue, $locale);
            try {
                $this->warmupTranslations([$entity], $locale);

                if ($this->cache->has($tuuidValue, $locale)) {
                    $this->cache->unmarkInProgress($tuuidValue, $locale);

                    return $this->cache->get($tuuidValue, $locale);
                }
            } catch (\Throwable $e) {
                $this->cache->unmarkInProgress($tuuidValue, $locale);
                throw $e;
            }
        }

        // Iterate through all registered translation handlers
        foreach ($this->handlers as $handler) {
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

                // 1. Determine if the top-level property is Shared
                if ($this->attributeHelper->isSharedAmongstTranslations($property)) {
                    $this->logDebug('Attribute detected: SharedAmongstTranslations', [
                        'property' => $property->name,
                        'class'    => $property->class,
                        'action'   => 'sharing value across translations',
                    ]);

                    return $handler->handleSharedAmongstTranslations($args);
                }

                // 2. Handle EmptyOnTranslate for this top-level property
                if ($this->attributeHelper->isEmptyOnTranslate($property)) {
                    if (!$this->attributeHelper->isNullable($property)) {
                        throw new \LogicException(sprintf('The property %s::%s cannot use EmptyOnTranslate because it is not nullable.', $property->class, $property->name));
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
                $this->cache->unmarkInProgress($entity->getTuuid()->getValue(), $locale);

                $this->logDebug('Translation complete', [
                    'class'         => $translated::class,
                    'target_locale' => $translated->getLocale(),
                ]);
            }

            return $translated;
        }

        return $entity;
    }

    public function addTranslationHandler(TranslationHandlerInterface $handler, int|null $priority = null): void
    {
        if (null === $priority) {
            $this->handlers[] = $handler;
        } else {
            $this->handlers[$priority] = $handler;
        }
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
     * Batch-load translations for given entities and target locale.
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
            if ($this->cache->has($tuuid, $locale)) {
                continue;
            }
            $byClass[$entity::class][] = $tuuid;
        }

        foreach ($byClass as $class => $tuuids) {
            $qb = $this->entityManager->createQueryBuilder()
                ->select('t')
                ->from($class, 't')
                ->where('t.tuuid IN (:tuuids)')
                ->andWhere('t.locale = :locale')
                ->setParameter('tuuids', $tuuids)
                ->setParameter('locale', $locale);

            /** @var array<TranslatableInterface>|null $translations */
            $translations = $qb->getQuery()->getResult();

            foreach ($translations ?? [] as $translation) {
                $this->cache->set(
                    $translation->getTuuid()->getValue(),
                    $translation->getLocale() ?? $locale,
                    $translation,
                );
            }
        }
    }
}
