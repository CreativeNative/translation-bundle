<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Event\TranslateEvent;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * ToDo: Introduce a Translation Cache Service
 * See GitHub Issue: https://github.com/CreativeNative/translation-bundle/issues/3
 *
 * ToDo: Improve #[EmptyOnTranslate] handling for non-nullable and scalar fields
 * See GitHub Issue: https://github.com/CreativeNative/translation-bundle/issues/2
 */
final class EntityTranslator implements EntityTranslatorInterface
{
    /** @var array<TranslationHandlerInterface> */
    private array $handlers = [];

    /**
     * Translation cache:
     * - First key: tuuid
     * - Second key: locale
     * - Value: Translated entity
     *
     * @var array<string, array<string, TranslatableInterface>>
     */
    private array $translationCache = [];

    /**
     * Tracks entities currently being translated (to avoid infinite recursion).
     *
     * @var array<string, true>
     */
    private array $inProgress = [];

    /**
     * @param array<string> $locales
     */
    public function __construct(
        #[Autowire(param: 'tmi_translation.default_locale')]
        private readonly string $defaultLocale,
        private readonly array $locales,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AttributeHelper $attributeHelper,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function translate(TranslatableInterface $entity, string $locale): TranslatableInterface
    {
        return $this->processTranslation(new TranslationArgs($entity, $entity->getLocale(), $locale));
    }

    /**
     * Process translation for a given entity or property.
     *
     * This method handles:
     *  - Top-level entity translation
     *  - Properties with #[SharedAmongstTranslations] or #[EmptyOnTranslate]
     *  - Embedded properties that may contain shared or empty attributes internally
     *
     * @param TranslationArgs $args Contains the entity or property to translate, source/target locales, and parent entity.
     * @return mixed Translated entity, embedded, or property value according to attribute rules
     */
    public function processTranslation(TranslationArgs $args): mixed
    {
        $entity = $args->getDataToBeTranslated();
        $locale = $args->getTargetLocale() ?? $this->defaultLocale;

        // Validate that the requested locale is allowed
        if (!in_array($locale, $this->locales, true)) {
            throw new LogicException(sprintf(
                'Locale "%s" is not allowed. Allowed locales: %s',
                $locale,
                implode(', ', $this->locales)
            ));
        }

        // Handle top-level entities that implement TranslatableInterface
        if ($entity instanceof TranslatableInterface) {
            $tuuid = $entity->getTuuid();
            $cacheKey = $tuuid ? $tuuid . ':' . $locale : null;

            // Return cached translation immediately if available
            if ($cacheKey && isset($this->translationCache[$tuuid][$locale])) {
                return $this->translationCache[$tuuid][$locale];
            }

            // Detect cycles to avoid infinite recursion
            if ($cacheKey && isset($this->inProgress[$cacheKey])) {
                return $entity;
            }

            // Mark as in-progress and attempt to warm up existing translations from the database
            if ($cacheKey) {
                $this->inProgress[$cacheKey] = true;
                // Warmup existing translations from DB
                $this->warmupTranslations([$entity], $locale);

                if (isset($this->translationCache[$tuuid][$locale])) {
                    // @codeCoverageIgnoreStart
                    unset($this->inProgress[$cacheKey]);
                    return $this->translationCache[$tuuid][$locale];
                    // @codeCoverageIgnoreEnd
                }
            }
        }

        // Iterate through all registered translation handlers
        foreach ($this->handlers as $handler) {
            if (!$handler->supports($args)) {
                continue;
            }

            // Dispatch PRE_TRANSLATE event for top-level entities
            if ($entity instanceof TranslatableInterface) {
                $this->eventDispatcher->dispatch(
                    new TranslateEvent($entity, $locale),
                    TranslateEvent::PRE_TRANSLATE
                );
            }

            // Handle attribute logic if a specific property is set in TranslationArgs
            $property = $args->getProperty();
            if ($property instanceof ReflectionProperty) {
                // 1. Determine if the top-level property is Shared
                if ($this->attributeHelper->isSharedAmongstTranslations($property)) {
                    return $handler->handleSharedAmongstTranslations($args);
                }

                // 2. Handle EmptyOnTranslate for this top-level property
                if ($this->attributeHelper->isEmptyOnTranslate($property)) {
                    if (!$this->attributeHelper->isNullable($property)) {
                        throw new LogicException(sprintf(
                            'The property %s::%s cannot use EmptyOnTranslate because it is not nullable.',
                            $property->class,
                            $property->name
                        ));
                    }
                    return $handler->handleEmptyOnTranslate($args);
                }

                // Handle embeddable (even if they are NOT marked EmptyOnTranslate or Shared)
                // ToDo: Support Embedded Entities with Both Shared and EmptyOnTranslate Properties
                // https://github.com/CreativeNative/translation-bundle/issues/6
                if ($this->attributeHelper->isEmbedded($property)) {
                    // 1. Process empty able properties inside the embedded object
                    $emptyResult = $handler->handleEmptyOnTranslate($args);

                    // 2. Process shared properties inside the embedded object
                    $sharedResult = $handler->handleSharedAmongstTranslations($args);

                    // 3. Decide which result to return
                    $original = $args->getDataToBeTranslated();
                    if ($emptyResult !== $original) {
                        return $emptyResult;
                    }

                    return $sharedResult;
                }
            }

            $translated = $handler->translate($args);

            // POST_TRANSLATE event
            if ($entity instanceof TranslatableInterface && $translated instanceof TranslatableInterface) {
                $this->eventDispatcher->dispatch(
                    new TranslateEvent($entity, $locale, $translated),
                    TranslateEvent::POST_TRANSLATE
                );
            }

            // Store translation in cache for reuse
            if ($translated instanceof TranslatableInterface && $translated->getTuuid() !== null) {
                $this->translationCache[$translated->getTuuid()][$translated->getLocale()] = $translated;
            }

            // Remove from in-progress set
            if ($entity instanceof TranslatableInterface && $entity->getTuuid() !== null) {
                unset($this->inProgress[$entity->getTuuid() . ':' . $locale]);
            }

            return $translated;
        }

        return $entity;
    }

    /**
     * Batch-load translations for given entities and target locale.
     *
     * @template T of TranslatableInterface
     * @param array<T> $entities
     */
    private function warmupTranslations(array $entities, string $locale): void
    {
        $byClass = [];

        foreach ($entities as $entity) {
            if (!$entity instanceof TranslatableInterface) {
                continue;
            }
            $tuuid = $entity->getTuuid();
            if ($tuuid === null || isset($this->translationCache[$tuuid][$locale])) {
                continue;
            }
            $byClass[get_class($entity)][] = $tuuid;
        }

        /** @var class-string<TranslatableInterface> $class */
        foreach ($byClass as $class => $tuuids) {
            if (empty($tuuids)) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }

            $qb = $this->entityManager->createQueryBuilder()
                ->select('t')
                ->from($class, 't')
                ->where('t.tuuid IN (:tuuids)')
                ->andWhere('t.locale = :locale')
                ->setParameter('tuuids', $tuuids)
                ->setParameter('locale', $locale);

            /** @var array<TranslatableInterface> $translations */
            $translations = $qb->getQuery()->getResult();

            foreach ($translations as $translation) {
                // @codeCoverageIgnoreStart
                $this->translationCache[$translation->getTuuid()][$translation->getLocale()] = $translation;
                // @codeCoverageIgnoreEnd
            }
        }
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
}
