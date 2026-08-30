<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;
use Tmi\TranslationBundle\ValueObject\LocaleCompleteness;
use Tmi\TranslationBundle\ValueObject\TranslationStatus;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * Answers, per enabled locale, whether a Tuuid group has a variant and whether
 * that variant's translatable content is complete.
 *
 * Completeness is relative to a baseline variant — the default-locale row when
 * it exists, otherwise the first variant found: a variant is Complete when
 * every translatable property that is filled on the baseline is also filled on
 * the variant. Optional properties left empty on the baseline therefore never
 * count against the translations.
 *
 * "Translatable property" means every mapped column except the identifier, the
 * bundle's system columns (tuuid, locale, translations) and properties marked
 * #[SharedAmongstTranslations] — those are copied, not translated. Embedded
 * fields contribute their non-shared inner properties. Associations are not
 * inspected. "Filled" means not null and, for strings, not blank.
 *
 * @phpstan-type CheckedProperty array{owner: \ReflectionProperty|null, property: \ReflectionProperty}
 */
final class LocaleCompletenessResolver
{
    private const string LOCALE_FILTER = 'tmi_translation_locale_filter';

    /** @var list<string> */
    private const array SYSTEM_PROPERTIES = ['tuuid', 'locale', 'translations'];

    /** @var array<class-string, list<CheckedProperty>> */
    private array $checkedProperties = [];

    /**
     * @param list<string> $locales
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AttributeHelper $attributeHelper,
        #[Autowire(param: 'tmi_translation.default_locale')]
        private readonly string $defaultLocale,
        #[Autowire(param: 'kernel.enabled_locales')]
        private readonly array $locales,
    ) {
    }

    /**
     * @param class-string $class
     */
    public function resolve(string $class, Tuuid $tuuid): LocaleCompleteness
    {
        return $this->resolveBatch($class, [$tuuid])[(string) $tuuid];
    }

    public function resolveForEntity(TranslatableInterface $entity): LocaleCompleteness
    {
        return $this->resolve($entity::class, $entity->getTuuid());
    }

    /**
     * Batch variant: resolves many Tuuids with a single query — an admin list
     * rendering hundreds of rows must not issue N queries.
     *
     * @param class-string $class
     * @param list<Tuuid>  $tuuids
     *
     * @return array<string, LocaleCompleteness> keyed by Tuuid string; one entry per requested Tuuid
     */
    public function resolveBatch(string $class, array $tuuids): array
    {
        if ([] === $tuuids) {
            return [];
        }

        $variants = $this->loadVariants($class, $tuuids);

        $result = [];

        foreach ($tuuids as $tuuid) {
            $key          = (string) $tuuid;
            $result[$key] = $this->completenessOf($class, $tuuid, $variants[$key] ?? []);
        }

        return $result;
    }

    /**
     * @param class-string                          $class
     * @param array<string, TranslatableInterface> $byLocale
     */
    private function completenessOf(string $class, Tuuid $tuuid, array $byLocale): LocaleCompleteness
    {
        $baseline = $byLocale[$this->defaultLocale] ?? array_values($byLocale)[0] ?? null;
        $required = null === $baseline ? [] : $this->filledProperties($class, $baseline);

        $statuses = [];

        foreach ($this->locales as $locale) {
            $variant = $byLocale[$locale] ?? null;

            $statuses[$locale] = match (true) {
                null === $variant                        => TranslationStatus::Missing,
                $this->isMissingAny($variant, $required) => TranslationStatus::Incomplete,
                default                                  => TranslationStatus::Complete,
            };
        }

        return new LocaleCompleteness($tuuid, $statuses, $baseline?->getLocale());
    }

    /**
     * The checked properties that carry a value on the baseline variant — the
     * content a translation must provide to count as complete.
     *
     * @param class-string $class
     *
     * @return list<CheckedProperty>
     */
    private function filledProperties(string $class, TranslatableInterface $baseline): array
    {
        return array_values(array_filter(
            $this->checkedProperties($class),
            fn (array $checked): bool => $this->isFilled($baseline, $checked),
        ));
    }

    /**
     * @param list<CheckedProperty> $required
     */
    private function isMissingAny(TranslatableInterface $variant, array $required): bool
    {
        return array_any($required, fn (array $checked): bool => !$this->isFilled($variant, $checked));
    }

    /**
     * @param CheckedProperty $checked
     */
    private function isFilled(TranslatableInterface $entity, array $checked): bool
    {
        $owner  = $checked['owner'];
        $holder = $entity;

        if (null !== $owner) {
            // Doctrine always hydrates embeddables on a loaded entity.
            $embeddable = $owner->getValue($entity);
            assert(is_object($embeddable));
            $holder = $embeddable;
        }

        $value = $checked['property']->getValue($holder);

        if (null === $value) {
            return false;
        }

        if (is_string($value)) {
            return '' !== trim($value);
        }

        return true;
    }

    /**
     * Mapped, non-system, non-identifier, non-shared properties — the columns
     * that hold locale-specific content. Embedded fields are expanded into
     * their non-shared inner properties, mirroring how EmbeddedHandler and
     * SyncSharedTranslationsCommand resolve sharing.
     *
     * @param class-string $class
     *
     * @return list<CheckedProperty>
     */
    private function checkedProperties(string $class): array
    {
        if (isset($this->checkedProperties[$class])) {
            return $this->checkedProperties[$class];
        }

        $metadata   = $this->entityManager->getClassMetadata($class);
        $reflection = $metadata->getReflectionClass();
        $checked    = [];

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();

            if (in_array($name, self::SYSTEM_PROPERTIES, true)) {
                continue;
            }

            $embedded = $metadata->embeddedClasses[$name] ?? null;

            if (null !== $embedded) {
                foreach ($this->translatableEmbeddedProperties($property, $embedded->class) as $entry) {
                    $checked[] = $entry;
                }

                continue;
            }

            if (!$metadata->hasField($name) || $metadata->isIdentifier($name)) {
                continue;
            }

            if ($this->attributeHelper->isSharedAmongstTranslations($property)) {
                continue;
            }

            $checked[] = ['owner' => null, 'property' => $property];
        }

        return $this->checkedProperties[$class] = $checked;
    }

    /**
     * Expands one embedded field into its locale-specific inner properties —
     * the complement of what SyncSharedTranslationsCommand treats as shared.
     *
     * @param class-string $embeddableClass
     *
     * @return list<CheckedProperty>
     */
    private function translatableEmbeddedProperties(\ReflectionProperty $property, string $embeddableClass): array
    {
        // The whole embeddable is copied from the source — nothing to translate.
        if ($this->attributeHelper->isSharedAmongstTranslations($property)) {
            return [];
        }

        $embeddable  = new \ReflectionClass($embeddableClass);
        $classShared = $this->attributeHelper->classHasSharedAmongstTranslations($embeddable);
        $checked     = [];

        foreach ($embeddable->getProperties() as $inner) {
            if ($this->attributeHelper->isSharedAmongstTranslations($inner)) {
                continue;
            }

            // A class-level attribute shares every property that does not override it.
            if ($classShared && !$this->attributeHelper->isEmptyOnTranslate($inner)) {
                continue;
            }

            $checked[] = ['owner' => $property, 'property' => $inner];
        }

        return $checked;
    }

    /**
     * Loads every locale variant of the given Tuuids in one query, with the
     * locale filter suspended so all locales are visible.
     *
     * @param class-string $class
     * @param list<Tuuid>  $tuuids
     *
     * @return array<string, array<string, TranslatableInterface>> tuuid string => locale => entity
     */
    private function loadVariants(string $class, array $tuuids): array
    {
        $filters    = $this->entityManager->getFilters();
        $wasEnabled = $filters->has(self::LOCALE_FILTER) && $filters->isEnabled(self::LOCALE_FILTER);

        if ($wasEnabled) {
            $filters->disable(self::LOCALE_FILTER);
        }

        try {
            $tuuidStrings = array_map(static fn (Tuuid $tuuid): string => (string) $tuuid, $tuuids);

            /** @var list<TranslatableInterface> $results */
            $results = $this->entityManager->createQueryBuilder()
                ->select('t')
                ->from($class, 't')
                ->where('t.tuuid IN (:tuuids)')
                ->setParameter('tuuids', $tuuidStrings)
                ->getQuery()
                ->getResult();

            /** @var array<string, array<string, TranslatableInterface>> $grouped */
            $grouped = [];

            foreach ($results as $entity) {
                $grouped[(string) $entity->getTuuid()][$entity->getLocale() ?? ''] = $entity;
            }

            return $grouped;
        } finally {
            if ($wasEnabled) {
                $filters->enable(self::LOCALE_FILTER);
            }
        }
    }
}
