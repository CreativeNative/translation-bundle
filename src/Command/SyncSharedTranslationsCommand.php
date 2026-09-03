<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\TranslatableEntityLocator;
use Tmi\TranslationBundle\Utils\AttributeHelper;
use Tmi\TranslationBundle\Utils\ReflectionHelper;

/**
 * Retroactively propagates #[SharedAmongstTranslations] values across all
 * locale variants of each Tuuid.
 *
 * Shared values are copied source → translation only at translate() time, so
 * data created in one locale and translated later — or edited after the fact —
 * keeps the shared value on the source row alone. This command back-fills the
 * siblings from the canonical (default-locale) row.
 *
 * With --check the command writes nothing and exits non-zero as soon as any
 * shared value has drifted — writable or readonly — so CI can gate on
 * "no shared property has diverged".
 *
 * Each class's report names every property that drifted — a table of property
 * path, number of distinct tuuids affected, number of sibling rows affected,
 * and whether the property is writable — so an operator can see which fields
 * are diverging without re-running with --dry-run and reading source.
 *
 * @phpstan-type SharedProperty array{owner: \ReflectionProperty|null, property: \ReflectionProperty}
 * @phpstan-type SharedDrift array{tuuids: array<string, true>, rows: int, readonly: bool}
 */
#[AsCommand(
    name: 'tmi:translation:sync-shared',
    description: 'Propagate #[SharedAmongstTranslations] values across all locale variants.',
)]
final class SyncSharedTranslationsCommand extends Command
{
    private const string LOCALE_FILTER = 'tmi_translation_locale_filter';

    /** @var list<string> */
    private const array SYSTEM_PROPERTIES = ['tuuid', 'locale'];

    /**
     * Tuuid groups processed between EntityManager flush/clear cycles while streaming
     * a class. Bounds peak memory to O(batch size × locale count) instead of O(table),
     * no matter how many rows the class holds.
     */
    private const int SYNC_BATCH_SIZE = 10;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatableEntityLocator $locator,
        private readonly AttributeHelper $attributeHelper,
        private readonly string $defaultLocale,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report changes without writing them.')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Write nothing and exit non-zero when any shared value has drifted — for CI gates.')
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'Restrict the sync to a single entity class.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $check  = true           === $input->getOption('check');
        $dryRun = $check || true === $input->getOption('dry-run');

        $io->title('TMI Translation — Sync Shared Values'.($check ? ' (check)' : ($dryRun ? ' (dry run)' : '')));

        $classes = $this->locator->locate();

        /** @var string|null $only */
        $only = $input->getOption('entity');

        if (null !== $only) {
            // Checked against Doctrine's metadata, not membership in $classes:
            // the locator now names only the root of each inheritance hierarchy
            // (see TranslatableEntityLocator), so --entity must still accept a
            // concrete subclass that is not itself one of $classes's entries.
            if (!$this->isTranslatableEntity($only)) {
                $io->error(sprintf('"%s" is not a known translatable entity.', $only));

                return Command::FAILURE;
            }
            $classes = [$only];
        }

        if ([] === $classes) {
            $io->warning('No translatable entities found.');

            return Command::SUCCESS;
        }

        $filters    = $this->entityManager->getFilters();
        $wasEnabled = $filters->has(self::LOCALE_FILTER) && $filters->isEnabled(self::LOCALE_FILTER);

        if ($wasEnabled) {
            $filters->disable(self::LOCALE_FILTER);
        }

        $totalUpdated = 0;

        /** @var list<string> $readonlyDrift */
        $readonlyDrift = [];

        try {
            foreach ($classes as $class) {
                // syncClass() streams the class and flushes/clears its own batches, so
                // no additional flush is needed here once the loop completes.
                $totalUpdated += $this->syncClass($io, $class, !$dryRun, $readonlyDrift);
            }
        } finally {
            if ($wasEnabled) {
                $filters->enable(self::LOCALE_FILTER);
            }
        }

        if ([] !== $readonlyDrift) {
            $io->warning(sprintf(
                '%d readonly shared value(s) differ from the source and were left untouched.',
                count($readonlyDrift),
            ));
            $io->listing($readonlyDrift);
            $io->note('A readonly property cannot be written after hydration. Correct these rows manually or at the database level.');
        }

        if (0 === $totalUpdated) {
            if ([] === $readonlyDrift) {
                $io->success('All shared values are already in sync.');

                return Command::SUCCESS;
            }

            return Command::FAILURE;
        }

        if ($check) {
            $io->error(sprintf(
                '%d translation(s) carry shared values that differ from their source. Run tmi:translation:sync-shared to repair.',
                $totalUpdated,
            ));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            $dryRun ? '%d translation(s) would be updated.' : '%d translation(s) updated.',
            $totalUpdated,
        ));

        return [] === $readonlyDrift ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Whether --entity names a real, mapped, translatable entity — checked
     * against Doctrine's metadata directly (mirroring what
     * {@see TranslatableEntityLocator::locate()} itself tests for a class it
     * accepts) instead of membership in the already-resolved $classes list,
     * which only ever names hierarchy roots and would wrongly reject a
     * concrete subclass.
     *
     * @phpstan-assert-if-true class-string $class
     */
    private function isTranslatableEntity(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        if ($this->entityManager->getMetadataFactory()->isTransient($class)) {
            return false;
        }

        $metadata = $this->entityManager->getClassMetadata($class);

        if ($metadata->isMappedSuperclass) {
            return false;
        }

        return $metadata->getReflectionClass()->implementsInterface(TranslatableInterface::class);
    }

    /**
     * @param class-string  $class
     * @param list<string> &$readonlyDrift collects readonly values that differ but cannot be written
     *
     * @return int Number of sibling translations whose shared values changed
     */
    private function syncClass(SymfonyStyle $io, string $class, bool $apply, array &$readonlyDrift): int
    {
        $io->section($class);

        /** @var array<class-string, list<SharedProperty>> $sharedPropertiesCache */
        $sharedPropertiesCache = [];

        if (!$this->hierarchyHasSharedProperties($class, $sharedPropertiesCache)) {
            $io->writeln('No #[SharedAmongstTranslations] properties — skipped.');

            return 0;
        }

        /** @var array<string, SharedDrift> $drift */
        $drift = [];

        $updated = $this->syncStream($class, $sharedPropertiesCache, $apply, $readonlyDrift, $drift);

        $io->writeln(0 === $updated
            ? '<info>OK</info> — already in sync.'
            : sprintf('<comment>%d translation(s) need updating.</comment>', $updated));

        if ([] !== $drift) {
            $io->table(['Property', 'Tuuids', 'Rows', 'Writable'], self::driftRows($drift));
        }

        return $updated;
    }

    /**
     * Whether $class — or, when it roots an inheritance hierarchy, any of its
     * concrete subclasses — declares at least one #[SharedAmongstTranslations]
     * property. A root's own reflection walk never sees a subclass-only field
     * (@see sharedProperties()), so a hierarchy is only truly shared-free when
     * none of its concrete classes are.
     *
     * @param class-string                                 $class
     * @param array<class-string, list<SharedProperty>>    &$cache
     */
    private function hierarchyHasSharedProperties(string $class, array &$cache): bool
    {
        if ([] !== $this->sharedPropertiesFor($class, $cache)) {
            return true;
        }

        foreach ($this->entityManager->getClassMetadata($class)->subClasses as $subclass) {
            if ([] !== $this->sharedPropertiesFor($subclass, $cache)) {
                return true;
            }
        }

        return false;
    }

    /**
     * sharedProperties(), memoized per concrete class — syncStream() re-resolves
     * the shared properties for every tuuid group from that group's own hydrated
     * class (see its docblock), and a class recurs across many groups whenever a
     * hierarchy's table holds more than a handful of rows.
     *
     * @param class-string                              $class
     * @param array<class-string, list<SharedProperty>> &$cache
     *
     * @return list<SharedProperty>
     */
    private function sharedPropertiesFor(string $class, array &$cache): array
    {
        return $cache[$class] ??= $this->sharedProperties($class);
    }

    /**
     * Streams $class ordered by tuuid instead of loading the whole table with findAll():
     * sibling locale variants of the same tuuid land next to each other in the result
     * set, so each group can be synced and released before most of the table is even
     * hydrated. Peak memory stays a small, table-size-independent multiple of the
     * locale count instead of growing with the table.
     *
     * In --apply mode the EntityManager is flushed and the just-completed groups are
     * detached every self::SYNC_BATCH_SIZE groups (plus once more for the trailing
     * partial batch); in --check/--dry-run mode entities are only detached, since
     * nothing was written.
     *
     * Detaching is deliberately per-entity (self::flushBatch()'s $settled list), not a
     * blanket EntityManager::clear(): toIterable() has already hydrated the *next*
     * group's first row (the "lookahead" entity, used above to detect the tuuid
     * change) by the time a batch boundary is decided, and that entity has not been
     * synced yet -- it is still sitting in the freshly reset $group. clear() would
     * detach it too, and a property write to a detached entity is invisible to every
     * later flush(), so whichever group happens to land on a batch boundary would
     * silently lose its update. Restricting detachment to $settled -- entities whose
     * group has already been synced -- keeps the still-forming group's entities
     * attached until their own turn to be flushed.
     *
     * @param class-string                               $class
     * @param array<class-string, list<SharedProperty>> &$sharedPropertiesCache
     * @param list<string>                              &$readonlyDrift
     * @param array<string, SharedDrift>                &$drift
     */
    private function syncStream(string $class, array &$sharedPropertiesCache, bool $apply, array &$readonlyDrift, array &$drift): int
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from($class, 't')
            ->orderBy('t.tuuid')
            ->getQuery();

        $updated       = 0;
        $groupsInBatch = 0;
        $currentTuuid  = null;

        /** @var list<TranslatableInterface> $group */
        $group = [];
        /** @var list<TranslatableInterface> $settled */
        $settled = [];

        foreach ($query->toIterable() as $entity) {
            assert($entity instanceof TranslatableInterface);

            $tuuid = (string) $entity->getTuuid();

            if (null !== $currentTuuid && $tuuid !== $currentTuuid) {
                $updated += $this->syncGroup($group, $sharedPropertiesCache, $apply, $readonlyDrift, $drift);

                foreach ($group as $settledEntity) {
                    $settled[] = $settledEntity;
                }

                $group = [];

                if (++$groupsInBatch >= self::SYNC_BATCH_SIZE) {
                    $this->flushBatch($apply, $settled);
                    $settled       = [];
                    $groupsInBatch = 0;
                }
            }

            $currentTuuid = $tuuid;
            $group[]      = $entity;
        }

        if ([] !== $group) {
            $updated += $this->syncGroup($group, $sharedPropertiesCache, $apply, $readonlyDrift, $drift);

            foreach ($group as $settledEntity) {
                $settled[] = $settledEntity;
            }
        }

        $this->flushBatch($apply, $settled);

        return $updated;
    }

    /**
     * Persists whatever the given already-synced entities changed (apply mode only)
     * and detaches them, so the identity map never grows past one batch regardless of
     * table size. Only ever called with entities whose group has already run through
     * syncGroup() -- see the "lookahead entity" note on syncStream().
     *
     * @param list<TranslatableInterface> $settled
     */
    private function flushBatch(bool $apply, array $settled): void
    {
        if ($apply) {
            $this->entityManager->flush();
        }

        foreach ($settled as $entity) {
            $this->entityManager->detach($entity);
        }
    }

    /**
     * Shared properties are resolved from the group's own source (baseline)
     * instance, not from a class fixed for the whole streamed query: a
     * SINGLE_TABLE or JOINED hierarchy queried through its root hydrates each
     * group as its own concrete subclass, and a subclass may declare
     * #[SharedAmongstTranslations] properties the root's reflection never sees
     * (@see sharedProperties()). All variants of one tuuid are the same
     * logical record, so they share one concrete class -- resolving once per
     * group, from the source, already covers every sibling.
     *
     * @param list<TranslatableInterface>                $variants
     * @param array<class-string, list<SharedProperty>> &$sharedPropertiesCache
     * @param list<string>                               &$readonlyDrift
     * @param array<string, SharedDrift>                 &$drift
     */
    private function syncGroup(array $variants, array &$sharedPropertiesCache, bool $apply, array &$readonlyDrift, array &$drift): int
    {
        $source           = $this->pickSource($variants);
        $sharedProperties = $this->sharedPropertiesFor($source::class, $sharedPropertiesCache);
        $count            = 0;

        if ([] === $sharedProperties) {
            return 0;
        }

        foreach ($variants as $sibling) {
            if ($sibling === $source) {
                continue;
            }

            if ($this->syncSibling($source, $sibling, $sharedProperties, $apply, $readonlyDrift, $drift)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param list<SharedProperty>       $sharedProperties
     * @param list<string>              &$readonlyDrift
     * @param array<string, SharedDrift> &$drift
     */
    private function syncSibling(
        TranslatableInterface $source,
        TranslatableInterface $sibling,
        array $sharedProperties,
        bool $apply,
        array &$readonlyDrift,
        array &$drift,
    ): bool {
        $changed = false;

        foreach ($sharedProperties as $shared) {
            $property = $shared['property'];

            // Shared properties are mapped columns, so Doctrine has hydrated them on both
            // the source and the sibling. For embedded fields the values live on the
            // embeddable instance rather than the entity itself.
            $sourceOwner  = self::valueOwner($source, $shared);
            $siblingOwner = self::valueOwner($sibling, $shared);

            $value   = $property->getValue($sourceOwner);
            $current = $property->getValue($siblingOwner);

            if (self::valuesEqual($current, $value)) {
                continue;
            }

            // readonly + shared is a legal combination, but an already-hydrated readonly
            // property cannot be written -- reporting the drift beats crashing mid-run.
            if ($property->isReadOnly()) {
                $readonlyDrift[] = sprintf(
                    '%s::$%s (tuuid %s, locale %s)',
                    $sibling::class,
                    self::propertyPath($shared),
                    (string) $sibling->getTuuid(),
                    $sibling->getLocale() ?? 'none',
                );

                self::recordDrift($drift, $shared, $sibling, true);

                continue;
            }

            $changed = true;

            self::recordDrift($drift, $shared, $sibling, false);

            if ($apply) {
                // Clone mutable objects so locale variants do not share a reference;
                // enums are immutable singletons and must not be cloned.
                $copy = is_object($value) && !$value instanceof \UnitEnum ? clone $value : $value;
                $property->setValue($siblingOwner, $copy);
            }
        }

        return $changed;
    }

    /**
     * Records one drifted (property, sibling row) pair into the per-class drift
     * accumulator that {@see syncClass()} renders as a table -- keyed by property
     * path so writable and readonly drift on the same property share one row, and
     * counting distinct tuuids separately from row count so a property shared
     * across many siblings of the same record is not overcounted.
     *
     * @param array<string, SharedDrift> &$drift
     * @param SharedProperty               $shared
     */
    private static function recordDrift(array &$drift, array $shared, TranslatableInterface $sibling, bool $readonly): void
    {
        $path  = self::propertyPath($shared);
        $entry = $drift[$path] ?? ['tuuids' => [], 'rows' => 0, 'readonly' => $readonly];

        $entry['tuuids'][(string) $sibling->getTuuid()] = true;
        ++$entry['rows'];
        $entry['readonly'] = $readonly;

        $drift[$path] = $entry;
    }

    /**
     * @param array<string, SharedDrift> $drift
     *
     * @return list<list<string>>
     */
    private static function driftRows(array $drift): array
    {
        $rows = [];

        foreach ($drift as $property => $entry) {
            $rows[] = [$property, count($entry['tuuids']), $entry['rows'], $entry['readonly']];
        }

        usort($rows, static fn (array $a, array $b): int => $b[2] <=> $a[2]);

        return array_map(
            static fn (array $r): array => [$r[0], (string) $r[1], (string) $r[2], $r[3] ? 'no' : 'yes'],
            $rows,
        );
    }

    /**
     * The object actually holding the value: the entity itself, or the embeddable instance
     * for an embedded field. Doctrine always hydrates embeddables on a loaded entity.
     *
     * @param SharedProperty $shared
     */
    private static function valueOwner(TranslatableInterface $entity, array $shared): object
    {
        $owner = $shared['owner'];

        if (null === $owner) {
            return $entity;
        }

        $embeddable = $owner->getValue($entity);
        assert(is_object($embeddable));

        return $embeddable;
    }

    /**
     * @param SharedProperty $shared
     */
    private static function propertyPath(array $shared): string
    {
        $owner = $shared['owner'];

        return null === $owner
            ? $shared['property']->getName()
            : $owner->getName().'.'.$shared['property']->getName();
    }

    /**
     * Value equality that satisfies strict comparison rules — identical
     * scalars/instances, or objects of the same class with equal state.
     */
    private static function valuesEqual(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return true;
        }

        // DateTimeInterface objects compare by the instant they represent (timezone-aware)
        // under both "==" and "<=>" -- no need to serialize() either side. "<=>" is used
        // because project strict rules forbid "==".
        if ($a instanceof \DateTimeInterface && $b instanceof \DateTimeInterface) {
            return ($a <=> $b) === 0;
        }

        if (is_object($a) && is_object($b)) {
            return $a::class === $b::class && serialize($a) === serialize($b);
        }

        return false;
    }

    /**
     * @param list<TranslatableInterface> $variants
     */
    private function pickSource(array $variants): TranslatableInterface
    {
        foreach ($variants as $variant) {
            if ($variant->getLocale() === $this->defaultLocale) {
                return $variant;
            }
        }

        return $variants[0];
    }

    /**
     * Mapped-column properties carrying #[SharedAmongstTranslations], excluding
     * system columns. Associations are skipped — the bundle forbids shared
     * associations — and only mapped columns are considered so the values are
     * guaranteed to be hydrated on every locale variant.
     *
     * Embedded fields are expanded separately: they are not mapped fields on the entity,
     * and sharing can be declared on the embeddable class or on its inner properties.
     *
     * @param class-string $class
     *
     * @return list<SharedProperty>
     */
    private function sharedProperties(string $class): array
    {
        $metadata   = $this->entityManager->getClassMetadata($class);
        $reflection = $metadata->getReflectionClass();
        $shared     = [];

        foreach (ReflectionHelper::getHierarchyProperties($reflection) as $property) {
            $name = $property->getName();

            if (in_array($name, self::SYSTEM_PROPERTIES, true)) {
                continue;
            }

            $embedded = $metadata->embeddedClasses[$name] ?? null;

            if (null !== $embedded) {
                foreach ($this->sharedEmbeddedProperties($property, $embedded->class) as $entry) {
                    $shared[] = $entry;
                }

                continue;
            }

            if (!$metadata->hasField($name)) {
                continue;
            }

            if ($this->attributeHelper->isSharedAmongstTranslations($property)) {
                $shared[] = ['owner' => null, 'property' => $property];
            }
        }

        return $shared;
    }

    /**
     * Expands one embedded field into the values that must stay in sync, mirroring what
     * EmbeddedHandler does at translate time:
     * - #[SharedAmongstTranslations] on the entity property shares the whole embeddable
     *   (EmbeddedHandler::handleSharedAmongstTranslations returns a clone with the
     *   source's property values, not the source instance itself);
     * - otherwise each inner property is resolved on its own, where a class-level attribute
     *   acts as the default for every property that does not override it.
     *
     * @param class-string $embeddableClass
     *
     * @return list<SharedProperty>
     */
    private function sharedEmbeddedProperties(\ReflectionProperty $property, string $embeddableClass): array
    {
        $embeddable = new \ReflectionClass($embeddableClass);

        if (!$this->attributeHelper->isEmbeddableShared($embeddable, $property)) {
            return [];
        }

        if ($this->attributeHelper->isSharedAmongstTranslations($property)) {
            return [['owner' => null, 'property' => $property]];
        }

        $classShared = $this->attributeHelper->classHasSharedAmongstTranslations($embeddable);
        $shared      = [];

        foreach (ReflectionHelper::getHierarchyProperties($embeddable) as $inner) {
            if ($this->attributeHelper->isSharedAmongstTranslations($inner)) {
                $shared[] = ['owner' => $property, 'property' => $inner];

                continue;
            }

            // A class-level attribute applies to every property the property level does not
            // claim for itself.
            if ($classShared && !$this->attributeHelper->isEmptyOnTranslate($inner)) {
                $shared[] = ['owner' => $property, 'property' => $inner];
            }
        }

        return $shared;
    }
}
