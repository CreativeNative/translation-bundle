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
use Tmi\TranslationBundle\Doctrine\GroupBatch;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\SharedDriftScanner;
use Tmi\TranslationBundle\Doctrine\SharedValueSynchronizer;
use Tmi\TranslationBundle\Doctrine\TranslatableEntityLocator;
use Tmi\TranslationBundle\ValueObject\SharedValueSyncReport;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * Retroactively propagates #[SharedAmongstTranslations] values across all
 * locale variants of each Tuuid.
 *
 * With `propagate_shared_on_flush` on (the default) every edit already reaches
 * the siblings inside its own flush, and this command is the back-fill for what
 * predates it: rows written before the flag was on, rows written with it off,
 * and rows changed outside the ORM (a migration, a DBA, an import). With the
 * flag off it is the only thing that reconciles at all — `translate()` copies a
 * shared value exactly once, when a new variant is created, so an edit after
 * that keeps the value on the edited row alone. Either way this command
 * back-fills the siblings from the canonical (default-locale) row.
 *
 * What counts as shared, and how a value is copied, is decided by
 * {@see SharedValueSynchronizer} — the same discovery the flush-time
 * propagation uses, so the two never disagree: mapped columns, embeddables in
 * all three places sharing can be declared, and single-valued associations to
 * a non-translatable target. Tables are walked with
 * {@see LocaleVariantFinder::streamGroupedByTuuid()} and the source row is
 * chosen by {@see SharedDriftScanner::pickSource()}, both shared with the
 * read-only {@see SharedDriftScanner}. This command only adds the batched
 * flush/detach cycle of the write mode ({@see GroupBatch}) and the reporting
 * around it.
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
 */
#[AsCommand(
    name: 'tmi:translation:sync-shared',
    description: 'Propagate #[SharedAmongstTranslations] values across all locale variants.',
)]
final class SyncSharedTranslationsCommand extends Command
{
    private readonly SharedValueRenderer $renderer;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatableEntityLocator $locator,
        private readonly LocaleVariantFinder $finder,
        private readonly SharedValueSynchronizer $synchronizer,
        private readonly SharedDriftScanner $scanner,
        private readonly string $defaultLocale,
    ) {
        parent::__construct();

        $this->renderer = new SharedValueRenderer($entityManager);
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report changes without writing them.')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Write nothing and exit non-zero when any shared value has drifted — for CI gates.')
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'Restrict the sync to a single entity class.')
            ->addOption('tuuid', null, InputOption::VALUE_REQUIRED, 'Restrict the sync to one record: every locale variant of this Tuuid (searched in every translatable class, or only in --entity).')
            ->addOption('source-locale', null, InputOption::VALUE_REQUIRED, 'With --tuuid: copy FROM this locale\'s row instead of the default-locale row — the targeted repair for a record edited in a non-default locale.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $mode = RunMode::fromInput($input);

        /** @var string|null $tuuidOption */
        $tuuidOption = $input->getOption('tuuid');
        /** @var string|null $sourceLocale */
        $sourceLocale = $input->getOption('source-locale');
        /** @var string|null $only */
        $only = $input->getOption('entity');

        $io->title('TMI Translation — Sync Shared Values'.$mode->titleSuffix());

        if (null !== $sourceLocale && null === $tuuidOption) {
            $io->error('--source-locale is only accepted together with --tuuid: which row is the source is a per-record decision, never a global one.');

            return Command::FAILURE;
        }

        $classes = $this->resolveClasses($io, $only);

        if (null === $classes) {
            return Command::FAILURE;
        }

        if ([] === $classes) {
            $io->warning('No translatable entities found.');

            return Command::SUCCESS;
        }

        if (null === $tuuidOption && $mode->writes() && !$this->confirmWholeTableWrite($io, $input)) {
            return Command::SUCCESS;
        }

        $run = new SyncSharedRun();

        $totalUpdated = null === $tuuidOption
            ? $this->runWholeTable($io, $classes, $mode, $run)
            : $this->syncOneRecord($io, $classes, $tuuidOption, $sourceLocale, $mode, $run);

        if (null === $totalUpdated) {
            return Command::FAILURE;
        }

        return $this->summarize($io, $totalUpdated, $run, $mode);
    }

    /**
     * The classes to walk: every translatable hierarchy root, or the one class
     * `--entity` names. Null after an error (already reported to $io).
     *
     * @return list<class-string>|null
     */
    private function resolveClasses(SymfonyStyle $io, string|null $only): array|null
    {
        if (null === $only) {
            return $this->locator->locate();
        }

        // Checked against Doctrine's metadata, not membership in locate()'s list:
        // the locator names only the root of each inheritance hierarchy, and
        // --entity must still accept a concrete subclass.
        if (!$this->locator->isTranslatableEntity($only)) {
            $io->error(\sprintf('"%s" is not a known translatable entity.', $only));

            return null;
        }

        return [$only];
    }

    /**
     * Every class, every group, the default-locale row as the source.
     *
     * @param list<class-string> $classes
     */
    private function runWholeTable(SymfonyStyle $io, array $classes, RunMode $mode, SyncSharedRun $run): int
    {
        $totalUpdated = 0;

        foreach ($classes as $class) {
            // syncClass() streams the class and flushes/detaches its own batches, so
            // no additional flush is needed here once the loop completes.
            $totalUpdated += $this->syncClass($io, $class, $mode, $run);
        }

        return $totalUpdated;
    }

    /**
     * The whole-table write mode has no per-group source line to carry the warning
     * describeSource() carries for --tuuid, and it is the mode that can destroy an
     * edit: every group is copied FROM its default-locale row, so a record edited in
     * another locale is reverted to the stale default-locale values. Say so once,
     * before the first UPDATE -- and, on an interactive terminal, ask. A script
     * passes -n (--no-interaction) and gets the pre-5.2 behaviour: note, then write.
     * --dry-run and --check never reach this method.
     *
     * @return bool false when the operator declined; nothing was written
     */
    private function confirmWholeTableWrite(SymfonyStyle $io, InputInterface $input): bool
    {
        $io->note(\sprintf(
            'Write mode copies each record from its "%s" row (the default locale), or from the record\'s '
            .'first row when it has no "%s" variant. A record that was edited in ANOTHER locale is reverted '
            .'to the stale default-locale values by this run -- repair those one at a time first with '
            .'--tuuid=<uuid> --source-locale=<locale>, then re-run this. --dry-run and --check never write.',
            $this->defaultLocale,
            $this->defaultLocale,
        ));

        if (!$input->isInteractive()) {
            return true;
        }

        if ($io->confirm('Write mode reverts records edited in another locale to the default-locale values. Continue?', false)) {
            return true;
        }

        $io->note('Aborted, nothing written.');

        return false;
    }

    /**
     * With -v, one line per changed value: `<tuuid> <locale> <path>: <old> -> <new>`, so
     * an operator sees what a write overwrote (or what --dry-run would) instead of a
     * count. The synchronizer hands over raw values; {@see SharedValueRenderer} formats them,
     * which keeps the synchronizer free of Doctrine display concerns.
     */
    private function logChanges(SymfonyStyle $io, TranslatableInterface $sibling, SharedValueSyncReport $report): void
    {
        if (!$io->isVerbose()) {
            return;
        }

        foreach ($report->changes() as $change) {
            $io->text(\sprintf(
                '  %s %s %s: %s → %s',
                $sibling->getTuuid(),
                $sibling->getLocale() ?? '?',
                $change->path,
                $this->renderer->render($change->old),
                $this->renderer->render($change->new),
            ));
        }
    }

    /**
     * The closing summary and exit code, identical for a whole-table run and a
     * --tuuid run.
     */
    private function summarize(SymfonyStyle $io, int $totalUpdated, SyncSharedRun $run, RunMode $mode): int
    {
        if ([] !== $run->readonlyDrift) {
            $io->warning(\sprintf(
                '%d readonly shared value(s) differ from the source and were left untouched.',
                \count($run->readonlyDrift),
            ));
            $io->listing($run->readonlyDrift);
            $io->note('A readonly property cannot be written after hydration. Correct these rows manually or at the database level.');
        }

        if ([] !== $run->rootDrift) {
            $io->warning(\sprintf(
                '%d translation root reference(s) differ between sibling rows and were left untouched.',
                \count($run->rootDrift),
            ));
            $io->listing($run->rootDrift);
            $io->note('A root reference is an identity, not a value: this command never re-points it, because copying the default-locale row\'s root over its siblings would silently merge two objects into one. Run tmi:translation:adopt-root --check to classify the group.');
        }

        $clean = !$run->hasUnwritable();

        if (0 === $totalUpdated) {
            if ($clean) {
                $io->success('All shared values are already in sync.');

                return Command::SUCCESS;
            }

            return Command::FAILURE;
        }

        if ($mode->isCheck()) {
            $io->error(\sprintf(
                '%d translation(s) carry shared values that differ from their source. Run tmi:translation:sync-shared to repair.',
                $totalUpdated,
            ));

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            $mode->writes() ? '%d translation(s) updated.' : '%d translation(s) would be updated.',
            $totalUpdated,
        ));

        return $clean ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * The --tuuid path: one record, every locale variant, an explicit or the
     * canonical source row. Returns the number of siblings that changed, or
     * null after an error (already reported to $io).
     *
     * @param list<class-string> $classes
     */
    private function syncOneRecord(SymfonyStyle $io, array $classes, string $tuuidOption, string|null $sourceLocale, RunMode $mode, SyncSharedRun $run): int|null
    {
        if (!Uuid::isValid($tuuidOption)) {
            $io->error(\sprintf('"%s" is not a valid Tuuid.', $tuuidOption));

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
            $io->error(\sprintf('No locale variant of Tuuid %s found in %s.', $tuuid, 1 === \count($classes) ? $classes[0] : 'any translatable entity'));

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

                $io->error(\sprintf('Tuuid %s has no "%s" variant — available locales: %s.', $tuuid, $sourceLocale, implode(', ', $locales)));

                return null;
            }
        }

        $io->section(\sprintf('%s — Tuuid %s', $found, $tuuid));
        $io->writeln($this->describeSource($source, null !== $sourceLocale));

        $run->beginClass();

        $updated = $this->syncGroup($io, $group, $source, $mode, $run);

        if ($mode->writes()) {
            $this->entityManager->flush();
        }

        $this->reportClass($io, $updated, $run);

        return $updated;
    }

    /**
     * The `Source:` line of a --tuuid run, naming the RULE that picked the row
     * and not only the locale it landed on.
     *
     * Without --source-locale the row is chosen by {@see SharedDriftScanner::pickSource()}
     * — the default-locale variant, or the group's first row when it has none —
     * in every mode, --check included. Printing the bare locale made two
     * consecutive runs look like they contradicted each other: a repair with
     * `--source-locale=de_DE` followed by a plain `--check` reported
     * `Source: locale it_IT`, as if the tool had forgotten the decision. It had
     * not; --source-locale is honoured wherever it is passed, and a run that
     * does not pass it falls back to the rule. Saying so on the line itself is
     * what keeps the two readable together.
     */
    private function describeSource(TranslatableInterface $source, bool $named): string
    {
        $locale = $source->getLocale() ?? 'none';

        if ($named) {
            return \sprintf('Source: locale <info>%s</info> — named by --source-locale.', $locale);
        }

        if ($locale === $this->defaultLocale) {
            return \sprintf(
                'Source: locale <info>%s</info> — the default-locale rule, applied in every mode. '
                .'Pass --source-locale to copy from another row.',
                $locale,
            );
        }

        return \sprintf(
            'Source: locale <info>%s</info> — the group\'s first row: this record has no "%s" variant '
            .'for the default-locale rule to pick. Pass --source-locale to copy from another row.',
            $locale,
            $this->defaultLocale,
        );
    }

    /**
     * @param class-string $class
     *
     * @return int Number of sibling translations whose shared values changed
     */
    private function syncClass(SymfonyStyle $io, string $class, RunMode $mode, SyncSharedRun $run): int
    {
        $io->section($class);

        if (!$this->hierarchyHasSharedProperties($class)) {
            $io->writeln('No #[SharedAmongstTranslations] properties — skipped.');

            return 0;
        }

        $run->beginClass();

        $updated = $this->syncStream($io, $class, $mode, $run);

        $this->reportClass($io, $updated, $run);

        return $updated;
    }

    private function reportClass(SymfonyStyle $io, int $updated, SyncSharedRun $run): void
    {
        $io->writeln(0 === $updated
            ? '<info>OK</info> — already in sync.'
            : \sprintf('<comment>%d translation(s) need updating.</comment>', $updated));

        if ([] !== $run->drift) {
            $io->table(['Property', 'Tuuids', 'Rows', 'Writable'], $run->driftRows());
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
     * syncs each Tuuid group as it completes; {@see GroupBatch} flushes (write
     * mode) and detaches the settled groups in batches, so peak memory stays a
     * small, table-size-independent multiple of the locale count.
     *
     * @param class-string $class
     */
    private function syncStream(SymfonyStyle $io, string $class, RunMode $mode, SyncSharedRun $run): int
    {
        $updated = 0;
        $batch   = new GroupBatch($this->entityManager, $mode->writes());

        foreach ($this->finder->streamGroupedByTuuid($class) as $group) {
            $updated += $this->syncGroup($io, $group, $this->scanner->pickSource($group), $mode, $run);

            $batch->settle($group);
            $batch->tick();
        }

        $batch->finish();

        return $updated;
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
     */
    private function syncGroup(SymfonyStyle $io, array $variants, TranslatableInterface $source, RunMode $mode, SyncSharedRun $run): int
    {
        $count = 0;

        foreach ($variants as $sibling) {
            if ($sibling === $source) {
                continue;
            }

            if ($this->syncSibling($io, $source, $sibling, $mode, $run)) {
                ++$count;
            }
        }

        return $count;
    }

    private function syncSibling(SymfonyStyle $io, TranslatableInterface $source, TranslatableInterface $sibling, RunMode $mode, SyncSharedRun $run): bool
    {
        $report = $mode->writes()
            ? $this->synchronizer->sync($source, $sibling)
            : $this->synchronizer->compare($source, $sibling);

        $this->logChanges($io, $sibling, $report);

        foreach ($report->readonlyDrift() as $path) {
            $run->noteUnwritable($sibling, $path, false);
        }

        foreach ($report->rootDrift() as $path) {
            $run->noteUnwritable($sibling, $path, true);
        }

        foreach ($report->changed() as $path) {
            $run->recordDrift($path, $sibling, false);
        }

        return $report->hasChanges();
    }
}
