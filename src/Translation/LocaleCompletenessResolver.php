<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\SharedValueSynchronizer;
use Tmi\TranslationBundle\Utils\ReflectionHelper;
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
 * bundle's system columns (tuuid, locale) and properties marked
 * #[SharedAmongstTranslations] — those are copied, not translated. Embedded
 * fields contribute their non-shared inner properties. Associations are not
 * inspected. "Filled" means not null and, for strings, not blank.
 *
 * The checked-property set is resolved from each Tuuid group's own baseline
 * instance, not from the class {@see resolveBatch()} was called with: a batch
 * spanning an inheritance hierarchy mixes tuuids belonging to different
 * concrete subclasses, each of which may declare its own translatable or
 * shared properties on top of the root's.
 *
 * @phpstan-type CheckedProperty array{owner: \ReflectionProperty|null, property: \ReflectionProperty}
 */
final class LocaleCompletenessResolver
{
    /** @var list<string> */
    private const array SYSTEM_PROPERTIES = ['tuuid', 'locale'];

    /** @var array<class-string, list<CheckedProperty>> */
    private array $checkedProperties = [];

    /**
     * @param list<string> $locales
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SharedValueSynchronizer $synchronizer,
        #[Autowire(param: 'tmi_translation.default_locale')]
        private readonly string $defaultLocale,
        #[Autowire(param: 'kernel.enabled_locales')]
        private readonly array $locales,
        private readonly LocaleVariantFinder $finder,
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
            $result[$key] = $this->completenessOf($tuuid, $variants[$key] ?? []);
        }

        return $result;
    }

    /**
     * @param array<string, TranslatableInterface> $byLocale
     */
    private function completenessOf(Tuuid $tuuid, array $byLocale): LocaleCompleteness
    {
        $baseline = $byLocale[$this->defaultLocale] ?? array_values($byLocale)[0] ?? null;

        // Resolved from the baseline's own concrete class, not the (possibly
        // polymorphic root) class the batch was queried with: a batch spanning
        // an inheritance hierarchy mixes tuuids belonging to different concrete
        // subclasses, each with its own checked properties (checkedProperties()
        // caches per class, so this costs a lookup, not a walk, once a class has
        // been seen).
        $required = null === $baseline ? [] : $this->filledProperties($baseline::class, $baseline);

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
     * their non-shared inner properties. What counts as shared is the
     * complement of {@see SharedValueSynchronizer::sharedProperties()} — the
     * one discovery behind `tmi:translation:sync-shared`, the flush-time
     * propagation and this resolver, so the three can never disagree.
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
        $shared     = $this->sharedPaths($class);
        $checked    = [];

        foreach (ReflectionHelper::getHierarchyProperties($reflection) as $property) {
            $name = $property->getName();

            if (in_array($name, self::SYSTEM_PROPERTIES, true) || isset($shared[$name])) {
                continue;
            }

            $embedded = $metadata->embeddedClasses[$name] ?? null;

            if (null !== $embedded) {
                foreach ($this->translatableEmbeddedProperties($property, $embedded->class, $shared) as $entry) {
                    $checked[] = $entry;
                }

                continue;
            }

            if (!$metadata->hasField($name) || $metadata->isIdentifier($name)) {
                continue;
            }

            $checked[] = ['owner' => null, 'property' => $property];
        }

        return $this->checkedProperties[$class] = $checked;
    }

    /**
     * Every shared path of $class as a lookup set: the property path of each
     * shared entry (`field`, `embedded.field`, or `embedded` for a whole shared
     * embeddable) plus its change-set paths (`embedded.field` for every column
     * of a whole shared embeddable).
     *
     * @param class-string $class
     *
     * @return array<string, true>
     */
    private function sharedPaths(string $class): array
    {
        $paths = [];

        foreach ($this->synchronizer->sharedProperties($class) as $shared) {
            $paths[$shared['path']] = true;

            foreach ($shared['changeSetPaths'] as $path) {
                $paths[$path] = true;
            }
        }

        return $paths;
    }

    /**
     * Expands one embedded field into its locale-specific inner properties —
     * every inner property whose `embedded.field` path is not shared. A whole
     * shared embeddable never reaches this method: its own path is in $shared
     * and {@see checkedProperties()} skips it outright.
     *
     * @param class-string        $embeddableClass
     * @param array<string, true> $shared
     *
     * @return list<CheckedProperty>
     */
    private function translatableEmbeddedProperties(\ReflectionProperty $property, string $embeddableClass, array $shared): array
    {
        $checked = [];

        foreach (ReflectionHelper::getHierarchyProperties(new \ReflectionClass($embeddableClass)) as $inner) {
            if (isset($shared[$property->getName().'.'.$inner->getName()])) {
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
        return $this->finder->findAllLocaleVariantsBatch($class, $tuuids);
    }
}
