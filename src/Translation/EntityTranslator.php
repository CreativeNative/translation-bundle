<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;

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

    public function processTranslation(TranslationArgs $args): mixed
    {
        $entity = $args->getDataToBeTranslated();

        if ($entity instanceof TranslatableInterface) {
            $tuuid = $entity->getTuuid();
            $locale = $args->getTargetLocale();

            if ($tuuid !== null) {
                $cacheKey = $tuuid . ':' . $locale;

                // Already cached? Return it immediately.
                if (isset($this->translationCache[$tuuid][$locale])) {
                    return $this->translationCache[$tuuid][$locale];
                }

                // Cycle detection: If we hit an entity currently being translated, return original.
                if (isset($this->inProgress[$cacheKey])) {
                    return $entity;
                }

                // Mark as in progress
                $this->inProgress[$cacheKey] = true;

                // Try to load existing translations from DB
                $this->warmupTranslations([$entity], $locale);

                if (isset($this->translationCache[$tuuid][$locale])) {
                    unset($this->inProgress[$cacheKey]);
                    return $this->translationCache[$tuuid][$locale];
                }
            }
        }

        foreach ($this->handlers as $handler) {
            if ($handler->supports($args)) {
                if (null !== $args->getProperty()) {
                    if ($this->attributeHelper->isSharedAmongstTranslations($args->getProperty())) {
                        return $handler->handleSharedAmongstTranslations($args);
                    }

                    if ($this->attributeHelper->isEmptyOnTranslate($args->getProperty())) {
                        if (!$this->attributeHelper->isNullable($args->getProperty())) {
                            throw new LogicException(sprintf(
                                'The property %s::%s cannot use EmptyOnTranslate because it is not nullable.',
                                $args->getProperty()->class,
                                $args->getProperty()->name
                            ));
                        }
                        return $handler->handleEmptyOnTranslate($args);
                    }
                }

                $translated = $handler->translate($args);

                // Store translation in cache for reuse
                if ($translated instanceof TranslatableInterface && $translated->getTuuid() !== null) {
                    $this->translationCache[$translated->getTuuid()][$translated->getLocale()] = $translated;
                }

                // Remove from in-progress set
                if ($entity instanceof TranslatableInterface && $entity->getTuuid() !== null) {
                    unset($this->inProgress[$entity->getTuuid() . ':' . $args->getTargetLocale()]);
                }

                return $translated;
            }
        }

        return $entity;
    }

    /**
     * Batch-load translations for given entities and target locale.
     *
     * @param array<TranslatableInterface> $entities
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
            // @codeCoverageIgnoreStart
            if (empty($tuuids)) {
                continue;
            }
            // @codeCoverageIgnoreEnd

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
                $this->translationCache[$translation->getTuuid()][$translation->getLocale()] = $translation;
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
