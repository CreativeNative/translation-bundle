<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;
use Tmi\TranslationBundle\Utils\ReflectionHelper;
use Tmi\TranslationBundle\ValueObject\SharedValueSyncReport;

/**
 * Copies #[SharedAmongstTranslations] values from one locale variant onto its
 * sibling locale variants -- the EDITED row is the source.
 *
 * A translatable entity is one row per locale. `translate()` copies a shared
 * value from the source exactly once, when a NEW variant is created; nothing
 * in that pipeline reaches a sibling that already exists. This service is the
 * one place that does: {@see syncFrom()} loads every other variant of the
 * source's Tuuid through {@see LocaleVariantFinder} (locale filter suspended),
 * copies the source's shared values onto each one, and hands the siblings
 * back MANAGED and unflushed -- the caller keeps the transaction boundary.
 * {@see EventListener\SharedValuePropagationListener}
 * runs it from inside `onFlush` when `propagate_shared_on_flush` is enabled;
 * `tmi:translation:sync-shared` runs the per-sibling copy ({@see sync()} /
 * {@see compare()}) over whole tables with the default-locale row as source.
 *
 * What counts as shared, resolved once per concrete class and memoized for
 * the life of the process (attribute presence is immutable class metadata):
 *
 * - a mapped column carrying the attribute (system columns `tuuid`/`locale`
 *   excluded);
 * - an embeddable, in all three places the bundle honours sharing: the
 *   attribute on the entity property shares the whole embeddable (one entry,
 *   the object is cloned onto the sibling), on the embeddable class it shares
 *   every inner property not overridden with #[EmptyOnTranslate], on an inner
 *   property just that one -- mirroring `EmbeddedHandler` at translate time;
 * - a single-valued association whose target is NOT itself translatable,
 *   shared as the identical instance -- the bundle's own semantics for such a
 *   relation. An association to a translatable target is rejected at
 *   translate time (RuntimeException, v4.0) and is never propagated here;
 *   collections are never shared at all.
 *
 * Every entry carries two path shapes. `path` is the property path
 * `tmi:translation:sync-shared` prints (`field`, `embedded.field`, or
 * `embedded` for a whole shared embeddable). `changeSetPaths` are the keys
 * Doctrine's UnitOfWork uses in an entity change set -- identical to `path`
 * except for a whole shared embeddable, whose change set appears as one key
 * per mapped inner column (`embedded.street`, `embedded.city`, ...), which is
 * what lets the flush listener intersect a change set with this list.
 *
 * Value handling, identical to what the two former copies of this logic did
 * (the command's `syncSibling()` and TMI's app-side `SharedFieldFanOut`):
 * scalars are assigned; value objects are CLONED so two rows never share one
 * mutable instance; enums are immutable singletons and are never cloned;
 * associations are the identical instance; a readonly property that differs
 * is reported, not written; an uninitialized typed property on the source is
 * skipped, on the sibling it reads as null.
 *
 * @phpstan-type SharedProperty array{owner: \ReflectionProperty|null, property: \ReflectionProperty, association: bool, path: string, changeSetPaths: list<string>}
 */
final class SharedValueSynchronizer
{
    /** @var list<string> */
    private const array SYSTEM_PROPERTIES = ['tuuid', 'locale'];

    /**
     * Per real entity class (proxies resolved through Doctrine's metadata).
     *
     * @var array<class-string, list<SharedProperty>>
     */
    private array $sharedProperties = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LocaleVariantFinder $finder,
        private readonly AttributeHelper $attributeHelper,
    ) {
    }

    /**
     * Copies the shared values of $source onto every other locale variant of
     * its Tuuid. The siblings are loaded managed and are NOT flushed.
     *
     * @param list<string>|null $onlyProperties restrict the copy to these property paths --
     *                                          either shape from the class docblock above
     *                                          (`salePrice`, `address.street`, `address`)
     *
     * @return list<TranslatableInterface> the siblings that received at least one changed value
     */
    public function syncFrom(TranslatableInterface $source, array|null $onlyProperties = null): array
    {
        $changed = [];

        foreach ($this->siblingsOf($source) as $sibling) {
            if ($this->sync($source, $sibling, $onlyProperties)->hasChanges()) {
                $changed[] = $sibling;
            }
        }

        return $changed;
    }

    /**
     * Every other locale variant of $source's Tuuid, managed, with the locale
     * filter suspended for the lookup. The source itself is left out by
     * identity and, for a source that is not (yet) managed, by locale.
     *
     * @return list<TranslatableInterface>
     */
    public function siblingsOf(TranslatableInterface $source): array
    {
        $class    = $this->entityManager->getClassMetadata($source::class)->getName();
        $siblings = [];

        foreach ($this->finder->findAllLocaleVariants($class, $source->getTuuid()) as $variant) {
            if ($variant === $source || $variant->getLocale() === $source->getLocale()) {
                continue;
            }

            $siblings[] = $variant;
        }

        return $siblings;
    }

    /**
     * Copies the shared values of $source that differ onto $sibling and reports
     * what was written and what could not be (readonly).
     *
     * @param list<string>|null $onlyProperties see {@see syncFrom()}
     */
    public function sync(TranslatableInterface $source, TranslatableInterface $sibling, array|null $onlyProperties = null): SharedValueSyncReport
    {
        return $this->reconcile($source, $sibling, $onlyProperties, true);
    }

    /**
     * {@see sync()} without writing: the same report, nothing touched.
     *
     * @param list<string>|null $onlyProperties see {@see syncFrom()}
     */
    public function compare(TranslatableInterface $source, TranslatableInterface $sibling, array|null $onlyProperties = null): SharedValueSyncReport
    {
        return $this->reconcile($source, $sibling, $onlyProperties, false);
    }

    /**
     * The shared properties of $class (a proxy class resolves to its entity
     * class), in declaration order, memoized per class.
     *
     * @param class-string $class
     *
     * @return list<SharedProperty>
     */
    public function sharedProperties(string $class): array
    {
        $metadata = $this->entityManager->getClassMetadata($class);

        return $this->sharedProperties[$metadata->getName()] ??= $this->discover($metadata);
    }

    /**
     * Value equality that satisfies strict comparison rules -- identical
     * scalars/instances, or objects of the same class with equal state.
     * DateTimeInterface objects compare by the instant they represent
     * (timezone-aware) under `<=>`; "==" is forbidden by the project's strict
     * rules, which is why the spaceship operator is used.
     */
    public static function valuesEqual(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if ($a instanceof \DateTimeInterface && $b instanceof \DateTimeInterface) {
            return ($a <=> $b) === 0;
        }

        if (is_object($a) && is_object($b)) {
            return $a::class === $b::class && serialize($a) === serialize($b);
        }

        return false;
    }

    /**
     * @param list<string>|null $onlyProperties
     */
    private function reconcile(TranslatableInterface $source, TranslatableInterface $sibling, array|null $onlyProperties, bool $write): SharedValueSyncReport
    {
        $changed       = [];
        $readonlyDrift = [];

        foreach ($this->sharedProperties($source::class) as $shared) {
            if (null !== $onlyProperties && !self::isSelected($shared, $onlyProperties)) {
                continue;
            }

            $property     = $shared['property'];
            $sourceOwner  = self::valueOwner($source, $shared);
            $siblingOwner = self::valueOwner($sibling, $shared);

            if (null === $sourceOwner || null === $siblingOwner || !$property->isInitialized($sourceOwner)) {
                continue;
            }

            $value   = $property->getValue($sourceOwner);
            $current = $property->isInitialized($siblingOwner) ? $property->getValue($siblingOwner) : null;

            // Two managed references to one row are always the same instance
            // (identity map), so an association compares by identity alone --
            // serialize() on an entity or proxy is neither cheap nor safe.
            if ($shared['association'] ? $current === $value : self::valuesEqual($current, $value)) {
                continue;
            }

            // readonly + shared is a legal combination, but an already-hydrated readonly
            // property cannot be written -- reporting the drift beats crashing mid-run.
            if ($property->isReadOnly()) {
                $readonlyDrift[] = $shared['path'];

                continue;
            }

            $changed[] = $shared['path'];

            if ($write) {
                $property->setValue(
                    $siblingOwner,
                    $shared['association'] || !is_object($value) || $value instanceof \UnitEnum ? $value : clone $value,
                );
            }
        }

        return new SharedValueSyncReport($changed, $readonlyDrift);
    }

    /**
     * Whether $onlyProperties names this entry, by its property path or by any
     * of its change-set paths.
     *
     * @param SharedProperty $shared
     * @param list<string>   $onlyProperties
     */
    private static function isSelected(array $shared, array $onlyProperties): bool
    {
        return in_array($shared['path'], $onlyProperties, true)
            || [] !== array_intersect($shared['changeSetPaths'], $onlyProperties);
    }

    /**
     * The object holding the value: the entity itself, or -- for an inner
     * property of an embeddable -- the embeddable instance, which is null when
     * the entity's embedded property is uninitialized or holds no object.
     *
     * @param SharedProperty $shared
     */
    private static function valueOwner(object $entity, array $shared): object|null
    {
        $owner = $shared['owner'];

        if (null === $owner) {
            return $entity;
        }

        if (!$owner->isInitialized($entity)) {
            return null;
        }

        $embeddable = $owner->getValue($entity);

        return is_object($embeddable) ? $embeddable : null;
    }

    /**
     * Walks the whole class hierarchy (a private property on a mapped
     * superclass is a real column, see ReflectionHelper) and resolves each
     * property against Doctrine's metadata so only hydrated state is ever
     * compared: mapped columns, embeddables, and to-one associations to a
     * non-translatable target.
     *
     * @param ClassMetadata<object> $metadata
     *
     * @return list<SharedProperty>
     */
    private function discover(ClassMetadata $metadata): array
    {
        $shared = [];

        foreach (ReflectionHelper::getHierarchyProperties($metadata->getReflectionClass()) as $property) {
            $name = $property->getName();

            if (in_array($name, self::SYSTEM_PROPERTIES, true)) {
                continue;
            }

            $embedded = $metadata->embeddedClasses[$name] ?? null;

            if (null !== $embedded) {
                foreach ($this->sharedEmbeddedProperties($metadata, $property, $embedded->class) as $entry) {
                    $shared[] = $entry;
                }

                continue;
            }

            if (!$this->attributeHelper->isSharedAmongstTranslations($property)) {
                continue;
            }

            if ($metadata->hasField($name)) {
                $shared[] = self::entry(null, $property, false, $name, [$name]);

                continue;
            }

            // A to-one association to a translatable target is rejected at translate time
            // and a to-many association is never shared -- neither is propagated.
            if (
                $metadata->isSingleValuedAssociation($name)
                && !is_a($metadata->getAssociationTargetClass($name), TranslatableInterface::class, true)
            ) {
                $shared[] = self::entry(null, $property, true, $name, [$name]);
            }
        }

        return $shared;
    }

    /**
     * Expands one embedded field into the values that must stay in sync,
     * mirroring how EmbeddedHandler resolves sharing at translate time:
     * - #[SharedAmongstTranslations] on the entity property shares the whole embeddable;
     * - otherwise each inner property is resolved on its own, where a class-level
     *   attribute acts as the default for every property that does not override it
     *   with #[EmptyOnTranslate].
     *
     * @param ClassMetadata<object> $metadata
     * @param class-string          $embeddableClass
     *
     * @return list<SharedProperty>
     */
    private function sharedEmbeddedProperties(ClassMetadata $metadata, \ReflectionProperty $property, string $embeddableClass): array
    {
        $embeddable = new \ReflectionClass($embeddableClass);

        if (!$this->attributeHelper->isEmbeddableShared($embeddable, $property)) {
            return [];
        }

        $name = $property->getName();

        if ($this->attributeHelper->isSharedAmongstTranslations($property)) {
            // The whole embeddable is one entry; its change set is one key per mapped inner column.
            $prefix = $name.'.';
            $paths  = array_values(array_filter(
                array_keys($metadata->fieldMappings),
                static fn (string $field): bool => str_starts_with($field, $prefix),
            ));

            return [self::entry(null, $property, false, $name, $paths)];
        }

        $classShared = $this->attributeHelper->classHasSharedAmongstTranslations($embeddable);
        $shared      = [];

        foreach (ReflectionHelper::getHierarchyProperties($embeddable) as $inner) {
            if (
                $this->attributeHelper->isSharedAmongstTranslations($inner)
                || ($classShared && !$this->attributeHelper->isEmptyOnTranslate($inner))
            ) {
                $path     = $name.'.'.$inner->getName();
                $shared[] = self::entry($property, $inner, false, $path, [$path]);
            }
        }

        return $shared;
    }

    /**
     * @param list<string> $changeSetPaths
     *
     * @return SharedProperty
     */
    private static function entry(\ReflectionProperty|null $owner, \ReflectionProperty $property, bool $association, string $path, array $changeSetPaths): array
    {
        return [
            'owner'          => $owner,
            'property'       => $property,
            'association'    => $association,
            'path'           => $path,
            'changeSetPaths' => $changeSetPaths,
        ];
    }
}
