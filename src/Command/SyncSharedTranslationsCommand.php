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
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\SharedDriftScanner;
use Tmi\TranslationBundle\Doctrine\SharedValueSynchronizer;
use Tmi\TranslationBundle\Doctrine\TranslatableEntityLocator;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * Retroactively propagates #[SharedAmongstTranslations] values across all
 * locale variants of each Tuuid.
 *
 * Shared values are copied source → translation only at translate() time, so
 * data created in one locale and translated later — or edited after the fact
 * without `propagate_shared_on_flush` — keeps the shared value on the source
 * row alone. This command back-fills the siblings from the canonical
 * (default-locale) row.
 *
 * What counts as shared, and how a value is copied, is decided by
 * {@see SharedValueSynchronizer} — the same discovery the opt-in flush-time
 * propagation uses, so the two never disagree: mapped columns, embeddables in
 * all three places sharing can be declared, and (since v4.1) single-valued
 * associations to a non-translatable target. Tables are walked with
 * {@see LocaleVariantFinder::streamGroupedByTuuid()} and the source row is
 * chosen by {@see SharedDriftScanner::pickSource()}, both shared with the
 * read-only {@see SharedDriftScanner}. This command only adds the batched
 * flush/detach cycle of the write mode and the reporting around it.
 *
 * With --check the command writes nothing and exits non-zero as soon as any
 * shared value has drifted — writable or readonly — so CI can gate on
 * "no shared property has diverged".
 *
 * With --tuuid the run is restricted to ONE record — every locale variant of
 * that Tuuid — and --source-locale names the row to copy FROM instead of the
 * default-locale row: the targeted repair for a record that was edited in a
 * non-default locale, where the whole-table write mode would overwrite the
 * edited row with the stale default-locale values. --source-locale is only
 * accepted together with --tuuid: a source locale is a per-record decision,
 * never a global one.
 *
 * Each class's report names every property that drifted — a table of property
 * path, number of distinct tuuids affected, number of sibling rows affected,
 * and whether the property is writable — so an operator can see which fields
 * are diverging without re-running with --dry-run and reading source.
 *
 * @phpstan-type SharedDrift array{tuuids: array<string, true>, rows: int, readonly: bool}
 */
#[AsCommand(
    name: 'tmi:translation:sync-shared',
    description: 'Propagate #[SharedAmongstTranslations] values across all locale variants.',
)]
final class SyncSharedTranslationsCommand extends Command
{
    /**
     * Tuuid groups processed between EntityManager flush/clear cycles while streaming
     * a class. Bounds peak memory to O(batch size × locale count) instead of O(table),
     * no matter how many rows the class holds.
     */
    private const int SYNC_BATCH_SIZE = 10;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatableEntityLocator $locator,
        private readonly LocaleVariantFinder $finder,
        private readonly SharedValueSynchronizer $synchronizer,
        private readonly SharedDriftScanner $scanner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report changes without writing them.')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Write nothing and exit non-zero when any shared value has drifted — for CI gates.')
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'Restrict the sync to a single entity class.')
            ->addOption('tuuid', null, InputOption::VALUE_REQUIRED, 'Restrict the sync to one record: every locale variant of this Tuuid (searched in every translatable class, or only in --entity).')
            ->addOption('source-locale', null, InputOption::VALUE_REQUIRED, 'With --tuuid: copy FROM this locale\'s row instead of the default-locale row — the targeted repair for a record edited in a non-default locale.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $check  = true           === $input->getOption('check');
        $dryRun = $check || true === $input->getOption('dry-run');

        /** @var string|null $tuuidOption */
        $tuuidOption = $input->getOption('tuuid');
        /** @var string|null $sourceLocale */
        $sourceLocale = $input->getOption('source-locale');

        $io->title('TMI Translation — Sync Shared Values'.($check ? ' (check)' : ($dryRun ? ' (dry run)' : '')));

        if (null !== $sourceLocale && null === $tuuidOption) {
            $io->error('--source-locale is only accepted together with --tuuid: which row is the source is a per-record decision, never a global one.');

            return Command::FAILURE;
        }

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

        /** @var list<string> $readonlyDrift */
        $readonlyDrift = [];

        if (null !== $tuuidOption) {
            $totalUpdated = $this->syncOneRecord($io, $classes, $tuuidOption, $sourceLocale, !$dryRun, $readonlyDrift);

            if (null === $totalUpdated) {
                return Command::FAILURE;
            }
        } else {
            $totalUpdated = 0;

            foreach ($classes as $class) {
                // syncClass() streams the class and flushes/clears its own batches, so
                // no additional flush is needed here once the loop completes.
                $totalUpdated += $this->syncClass($io, $class, !$dryRun, $readonlyDrift);
            }
        }

        return $this->summarize($io, $totalUpdated, $readonlyDrift, $check, $dryRun);
    }

    /**
     * The closing summary and exit code, identical for a whole-table run and a
     * --tuuid run.
     *
     * @param list<string> $readonlyDrift
     */
    private function summarize(SymfonyStyle $io, int $totalUpdated, array $readonlyDrift, bool $check, bool $dryRun): int
    {
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
     * The --tuuid path: one record, every locale variant, an explicit or the
     * canonical source row. Returns the number of siblings that changed, or
     * null after an error (already reported to $io).
     *
     * @param list<class-string> $classes
     * @param list<string>      &$readonlyDrift
     */
    private function syncOneRecord(SymfonyStyle $io, array $classes, string $tuuidOption, string|null $sourceLocale, bool $apply, array &$readonlyDrift): int|null
    {
        if (!Uuid::isValid($tuuidOption)) {
            $io->error(sprintf('"%s" is not a valid Tuuid.', $tuuidOption));

            return null;
        }

        $tuuid    = new Tuuid($tuuidOption);
        $found    = null;
        $variants = [];

        foreach ($classes as $class) {
            $variants = $this->finder->findAllLocaleVariants($class, $tuuid);

            if ([] !== $variants) {
                $found = $class;

                break;
            }
        }

        if (null === $found) {
            $io->error(sprintf('No locale variant of tuuid %s found in %s.', $tuuid, 1 === count($classes) ? $classes[0] : 'any translatable entity'));

            return null;
        }

        /** @var non-empty-list<TranslatableInterface> $group */
        $group = array_values($variants);

        if (null === $sourceLocale) {
            $source = $this->scanner->pickSource($group);
        } else {
            $source = $variants[$sourceLocale] ?? null;

            if (null === $source) {
                $locales = array_keys($variants);
                sort($locales);

                $io->error(sprintf('Tuuid %s has no "%s" variant — available locales: %s.', $tuuid, $sourceLocale, implode(', ', $locales)));

                return null;
            }
        }

        $io->section(sprintf('%s — tuuid %s', $found, $tuuid));
        $io->writeln(sprintf('Source: locale <info>%s</info>', $source->getLocale() ?? 'none'));

        /** @var array<string, SharedDrift> $drift */
        $drift = [];

        $updated = $this->syncGroup($group, $source, $apply, $readonlyDrift, $drift);

        if ($apply) {
            $this->entityManager->flush();
        }

        $this->reportClass($io, $updated, $drift);

        return $updated;
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

        if (!$this->hierarchyHasSharedProperties($class)) {
            $io->writeln('No #[SharedAmongstTranslations] properties — skipped.');

            return 0;
        }

        /** @var array<string, SharedDrift> $drift */
        $drift = [];

        $updated = $this->syncStream($class, $apply, $readonlyDrift, $drift);

        $this->reportClass($io, $updated, $drift);

        return $updated;
    }

    /**
     * @param array<string, SharedDrift> $drift
     */
    private function reportClass(SymfonyStyle $io, int $updated, array $drift): void
    {
        $io->writeln(0 === $updated
            ? '<info>OK</info> — already in sync.'
            : sprintf('<comment>%d translation(s) need updating.</comment>', $updated));

        if ([] !== $drift) {
            $io->table(['Property', 'Tuuids', 'Rows', 'Writable'], self::driftRows($drift));
        }
    }

    /**
     * Whether $class — or, when it roots an inheritance hierarchy, any of its
     * concrete subclasses — declares at least one #[SharedAmongstTranslations]
     * property. A root's own reflection walk never sees a subclass-only field,
     * so a hierarchy is only truly shared-free when none of its concrete
     * classes are. The synchronizer memoizes the answer per class.
     *
     * @param class-string $class
     */
    private function hierarchyHasSharedProperties(string $class): bool
    {
        if ([] !== $this->synchronizer->sharedProperties($class)) {
            return true;
        }

        foreach ($this->entityManager->getClassMetadata($class)->subClasses as $subclass) {
            if ([] !== $this->synchronizer->sharedProperties($subclass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Walks $class with {@see LocaleVariantFinder::streamGroupedByTuuid()} and
     * syncs each Tuuid group as it completes, so peak memory stays a small,
     * table-size-independent multiple of the locale count.
     *
     * In --apply mode the EntityManager is flushed and the just-completed groups are
     * detached every self::SYNC_BATCH_SIZE groups (plus once more for the trailing
     * partial batch); in --check/--dry-run mode entities are only detached, since
     * nothing was written.
     *
     * Detaching is deliberately per-entity (self::flushBatch()'s $settled list), not a
     * blanket EntityManager::clear(): the stream has already hydrated the *next*
     * group's first row (the "lookahead" entity) by the time a group is yielded, and
     * that entity has not been synced yet. clear() would detach it too, and a property
     * write to a detached entity is invisible to every later flush(), so whichever
     * group happens to land on a batch boundary would silently lose its update.
     * Restricting detachment to $settled -- entities whose group has already been
     * synced -- keeps the still-forming group's entities attached until their own
     * turn to be flushed.
     *
     * @param class-string               $class
     * @param list<string>              &$readonlyDrift
     * @param array<string, SharedDrift> &$drift
     */
    private function syncStream(string $class, bool $apply, array &$readonlyDrift, array &$drift): int
    {
        $updated       = 0;
        $groupsInBatch = 0;

        /** @var list<TranslatableInterface> $settled */
        $settled = [];

        foreach ($this->finder->streamGroupedByTuuid($class) as $group) {
            $updated += $this->syncGroup($group, $this->scanner->pickSource($group), $apply, $readonlyDrift, $drift);

            foreach ($group as $settledEntity) {
                $settled[] = $settledEntity;
            }

            if (++$groupsInBatch >= self::SYNC_BATCH_SIZE) {
                $this->flushBatch($apply, $settled);
                $settled       = [];
                $groupsInBatch = 0;
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
     * Syncs every sibling of one tuuid group against $source. The synchronizer
     * resolves the shared properties from the source's OWN concrete class, not
     * from a class fixed for the whole streamed query: a SINGLE_TABLE or JOINED
     * hierarchy queried through its root hydrates each group as its own
     * concrete subclass, and a subclass may declare
     * #[SharedAmongstTranslations] properties the root's reflection never sees.
     * All variants of one tuuid are the same logical record, so they share one
     * concrete class -- resolving from the source already covers every sibling.
     *
     * @param list<TranslatableInterface> $variants
     * @param list<string>               &$readonlyDrift
     * @param array<string, SharedDrift>  &$drift
     */
    private function syncGroup(array $variants, TranslatableInterface $source, bool $apply, array &$readonlyDrift, array &$drift): int
    {
        $count = 0;

        foreach ($variants as $sibling) {
            if ($sibling === $source) {
                continue;
            }

            if ($this->syncSibling($source, $sibling, $apply, $readonlyDrift, $drift)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param list<string>              &$readonlyDrift
     * @param array<string, SharedDrift> &$drift
     */
    private function syncSibling(
        TranslatableInterface $source,
        TranslatableInterface $sibling,
        bool $apply,
        array &$readonlyDrift,
        array &$drift,
    ): bool {
        $report = $apply
            ? $this->synchronizer->sync($source, $sibling)
            : $this->synchronizer->compare($source, $sibling);

        foreach ($report->readonlyDrift() as $path) {
            $readonlyDrift[] = sprintf(
                '%s::$%s (tuuid %s, locale %s)',
                $sibling::class,
                $path,
                (string) $sibling->getTuuid(),
                $sibling->getLocale() ?? 'none',
            );

            self::recordDrift($drift, $path, $sibling, true);
        }

        foreach ($report->changed() as $path) {
            self::recordDrift($drift, $path, $sibling, false);
        }

        return $report->hasChanges();
    }

    /**
     * Records one drifted (property, sibling row) pair into the per-class drift
     * accumulator that {@see reportClass()} renders as a table -- keyed by property
     * path so writable and readonly drift on the same property share one row, and
     * counting distinct tuuids separately from row count so a property shared
     * across many siblings of the same record is not overcounted.
     *
     * @param array<string, SharedDrift> &$drift
     */
    private static function recordDrift(array &$drift, string $path, TranslatableInterface $sibling, bool $readonly): void
    {
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
}
