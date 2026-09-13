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
use Tmi\TranslationBundle\Doctrine\GroupBatch;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;
use Tmi\TranslationBundle\Doctrine\Root\RootAdopterInterface;
use Tmi\TranslationBundle\Doctrine\Root\RootAdopterRegistry;
use Tmi\TranslationBundle\Doctrine\Root\RootCheckAggregator;
use Tmi\TranslationBundle\Doctrine\TranslatableEntityLocator;
use Tmi\TranslationBundle\Exception\RootAdoptionException;
use Tmi\TranslationBundle\Utils\AttributeHelper;
use Tmi\TranslationBundle\Utils\ReflectionHelper;

/**
 * Creates translation roots for the Tuuid groups that already exist, and proves the
 * root invariant for CI (5.1).
 *
 * A translation row's root reference (a ManyToOne typed to a TranslationRootInterface,
 * see AttributeHelper::isTranslationRootReference()) is filled for new objects by the
 * application's own constructor. The rows written BEFORE the root existed are this
 * command's job: for every class an adopter is registered for
 * ({@see RootAdopterInterface}, tag `tmi_translation.root_adopter`) it streams the
 * hierarchy root with {@see LocaleVariantFinder::streamGroupedByTuuid()} -- the finder
 * suspends the locale filter itself, load-bearing since a CLI has no firewall -- and
 * CLASSIFIES EVERY GROUP BEFORE WRITING ANYTHING:
 *
 * - mismatched -- rows disagree on rootClassFor() or coherenceKey(): a tuuid collision,
 *   a bad import. Checked first; never adopted.
 * - new        -- no row has a root.
 * - complete   -- every row has the same root, its tuuid equals the group's, and it is
 *   an instance of rootClassFor(): nothing to do (idempotency).
 * - partial    -- some rows have the one root, others none (an interrupted run).
 * - drift      -- a row's tuuid differs from its root's, or the root's class is not the
 *   one the rows imply.
 * - ambiguous  -- two or more distinct roots inside one group.
 *
 * Write mode refuses to touch the table while any group is mismatched, drift or
 * ambiguous: the full report is printed and the command exits FAILURE without a single
 * UPDATE. Otherwise it adopts, in batches of GroupBatch::SIZE GROUPS: a NEW group gets
 * `createRootFor()`'s root (refused if it already carries a tuuid or is not an instance
 * of rootClassFor()), which adopts the group's tuuid, is persisted, and is attached to
 * every row; a PARTIAL group has its missing rows attached to the EXISTING root
 * (self-healing). A group's persist and every attach complete before the batch's flush,
 * and the batch counter increments per group, never per row -- an interruption never
 * commits a half-attached group. Settled entities -- the rows AND their roots -- are
 * detached individually (the finder's lookahead row is already hydrated; never a
 * blanket clear()), so peak memory is a small multiple of the locale count however
 * large the table. The stream fetch-joins the root references, so a pass is one
 * query however many groups it classifies (see rootReferenceJoins()).
 *
 * --dry-run classifies and reports, writes nothing. --check implies --dry-run and fails
 * on ANY new, partial, drift, ambiguous or mismatched group, on any root without rows
 * (counted by the bundle with NOT EXISTS -- {@see RootCheckAggregator}) and on any
 * `tmi_translation.tuuid_orphan_counter` above zero. Every counter is printed, at 0 too;
 * an exception from a counter or from the bundle's own query shows as ERROR and fails
 * the check -- a broken counter must never look like a verified-clean one.
 *
 * --entity accepts a concrete SINGLE_TABLE leaf like the two other commands, but the
 * class streamed is ALWAYS the adopter's hierarchy root: a leaf-scoped run would mint a
 * root for its own rows and turn a sibling leaf's row of the same tuuid into an
 * ambiguous group on the next full run.
 *
 * @phpstan-type Classification array{kind: string, tuuid: string, locales: string, detail: string}
 * @phpstan-type RootReference array{class: class-string, property: string, root: class-string}
 */
#[AsCommand(
    name: 'tmi:translation:adopt-root',
    description: 'Create translation roots for the locale-variant groups that predate them; --check proves the root invariant for CI.',
)]
final class AdoptRootCommand extends Command
{
    public const string KIND_MISMATCHED = 'mismatched';
    public const string KIND_NEW        = 'new';
    public const string KIND_COMPLETE   = 'complete';
    public const string KIND_PARTIAL    = 'partial';
    public const string KIND_DRIFT      = 'drift';
    public const string KIND_AMBIGUOUS  = 'ambiguous';

    /** @var list<string> in report order */
    private const array KINDS = [
        self::KIND_COMPLETE,
        self::KIND_NEW,
        self::KIND_PARTIAL,
        self::KIND_DRIFT,
        self::KIND_AMBIGUOUS,
        self::KIND_MISMATCHED,
    ];

    /** @var list<string> a group of one of these kinds aborts write mode before the first write */
    private const array BLOCKING = [self::KIND_MISMATCHED, self::KIND_DRIFT, self::KIND_AMBIGUOUS];

    /** Anomaly groups listed per kind before the table is cut off with a count of the rest. */
    private const int MAX_LISTED_GROUPS = 20;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LocaleVariantFinder $finder,
        private readonly RootAdopterRegistry $adopters,
        private readonly RootCheckAggregator $checks,
        private readonly AttributeHelper $attributeHelper,
        private readonly TranslatableEntityLocator $locator,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Classify and report every group; write nothing.')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Write nothing and exit non-zero unless every group is complete, every root has rows and every orphan counter is 0 -- for CI gates.')
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'Restrict the run to one translatable class (its whole hierarchy is streamed).');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $mode = RunMode::fromInput($input);

        $io->title('TMI Translation — Adopt Translation Roots'.$mode->titleSuffix());

        /** @var string|null $only */
        $only     = $input->getOption('entity');
        $adopters = $this->resolveAdopters($io, $only);

        if (null === $adopters) {
            return Command::FAILURE;
        }

        if ([] === $adopters) {
            $io->success('No entity declares a translation root.');

            return Command::SUCCESS;
        }

        // --check's verdict: any group that is not complete, any root without rows, any
        // orphan counter above zero or erroring. Write mode prints the same tables for
        // information but is gated on its own refusal only ($aborted).
        $violated = false;
        $aborted  = false;
        $adopted  = 0;

        foreach ($adopters as $adopter) {
            ['violated' => $classViolated, 'aborted' => $classAborted, 'adopted' => $classAdopted] = $this->processAdopter($io, $adopter, $mode);

            $violated = $violated || $classViolated;
            $aborted  = $aborted  || $classAborted;
            $adopted += $classAdopted;
        }

        $violated = $this->reportOrphanCounters($io) || $violated;

        if ($mode->isCheck()) {
            if ($violated) {
                $io->error('The translation root invariant does not hold. Run tmi:translation:adopt-root to adopt new and partial groups; mismatched, drifted and ambiguous groups and orphan rows need a manual decision.');

                return Command::FAILURE;
            }

            $io->success('Every group has its root, every root has rows, every orphan counter is 0.');

            return Command::SUCCESS;
        }

        if ($aborted) {
            return Command::FAILURE;
        }

        $io->success($mode->writes() ? \sprintf('%d group(s) adopted.', $adopted) : 'Dry run complete -- nothing written.');

        return Command::SUCCESS;
    }

    /**
     * The adopters to run: every registered one, or the one serving the class
     * `--entity` names. Null after an error (already reported to $io).
     *
     * @return list<RootAdopterInterface>|null
     */
    private function resolveAdopters(SymfonyStyle $io, string|null $only): array|null
    {
        if (null === $only) {
            return $this->adopters->all();
        }

        if (!$this->locator->isTranslatableEntity($only)) {
            $io->error(\sprintf('"%s" is not a known translatable entity.', $only));

            return null;
        }

        $adopter = $this->adopters->adopterFor($only);

        if (null === $adopter) {
            $io->error(\sprintf('"%s" declares no translation root: no tmi_translation.root_adopter serves it or any of its ancestors.', $only));

            return null;
        }

        return [$adopter];
    }

    /**
     * One adopter's hierarchy: classify (pass 1), adopt in write mode when nothing
     * blocks (pass 2), then the roots-without-rows table. `violated` feeds --check's
     * verdict, `aborted` write mode's refusal, `adopted` the closing count.
     *
     * @return array{violated: bool, aborted: bool, adopted: int}
     */
    private function processAdopter(SymfonyStyle $io, RootAdopterInterface $adopter, RunMode $mode): array
    {
        $class = $this->entityManager->getClassMetadata($adopter->getTranslatableClass())->rootEntityName;

        $io->section($class);

        $tally = $this->classifyClass($io, $adopter, $class);

        $blocking = array_sum(array_intersect_key($tally, array_flip(self::BLOCKING))) > 0;
        $pending  = $tally[self::KIND_NEW] + $tally[self::KIND_PARTIAL];
        $aborted  = false;
        $adopted  = 0;

        if ($mode->writes()) {
            if ($blocking) {
                $io->error('Mismatched, drifted or ambiguous groups present -- aborting before the first write. Resolve them by hand, then re-run.');
                $aborted = true;
            } elseif ($pending > 0) {
                $adopted = $this->adoptClass($adopter, $class);
                $io->writeln(\sprintf('<info>%d group(s) adopted.</info>', $adopted));
            }
        }

        $rootsWithoutRows = $this->reportRootsWithoutRows($io, $class);

        return ['violated' => $blocking || $pending > 0 || $rootsWithoutRows, 'aborted' => $aborted, 'adopted' => $adopted];
    }

    /**
     * Pass 1: classify every group of $class, print the classification table and one
     * table per anomaly kind, detach each group. Returns the per-kind tally.
     *
     * @param class-string $class
     *
     * @return array<string, int>
     */
    private function classifyClass(SymfonyStyle $io, RootAdopterInterface $adopter, string $class): array
    {
        $tally = array_fill_keys(self::KINDS, 0);

        /** @var array<string, list<Classification>> $listed */
        $listed = [];
        $batch  = new GroupBatch($this->entityManager, false);

        foreach ($this->finder->streamGroupedByTuuid($class, $this->rootReferenceJoins($class)) as $group) {
            $classification = $this->classify($adopter, $group);
            $kind           = $classification['kind'];

            ++$tally[$kind];

            if (self::KIND_COMPLETE !== $kind && \count($listed[$kind] ?? []) < self::MAX_LISTED_GROUPS) {
                $listed[$kind][] = $classification;
            }

            $batch->settle($group);
            $batch->settle($this->rootsOf($adopter, $group));
            $batch->tick();
        }

        $batch->finish();

        $io->table(
            ['Classification', 'Groups'],
            array_map(static fn (string $kind): array => [$kind, (string) $tally[$kind]], self::KINDS),
        );

        foreach (self::KINDS as $kind) {
            if (!isset($listed[$kind])) {
                continue;
            }

            $io->writeln(\sprintf('<comment>%s groups</comment>', ucfirst($kind)));
            $io->table(
                ['Tuuid', 'Locales', 'Detail'],
                array_map(static fn (array $c): array => [$c['tuuid'], $c['locales'], $c['detail']], $listed[$kind]),
            );

            if ($tally[$kind] > self::MAX_LISTED_GROUPS) {
                $io->writeln(\sprintf('… and %d more %s group(s).', $tally[$kind] - self::MAX_LISTED_GROUPS, $kind));
            }
        }

        return $tally;
    }

    /**
     * Pass 2, write mode only, after pass 1 found no blocking group: adopt every NEW
     * group and heal every PARTIAL one, in batches of whole groups.
     *
     * @param class-string $class
     *
     * @return int groups adopted or healed
     */
    private function adoptClass(RootAdopterInterface $adopter, string $class): int
    {
        $adopted = 0;
        $batch   = new GroupBatch($this->entityManager, true);

        foreach ($this->finder->streamGroupedByTuuid($class, $this->rootReferenceJoins($class)) as $group) {
            $kind = $this->classify($adopter, $group)['kind'];

            if (self::KIND_NEW === $kind) {
                $this->adoptNewGroup($adopter, $group);
                ++$adopted;
            } elseif (self::KIND_PARTIAL === $kind) {
                $this->healPartialGroup($adopter, $group);
                ++$adopted;
            }

            // The group's root -- existing, healed onto, or just minted and persisted
            // -- is settled with its rows: detached after the batch's flush.
            $batch->settle($group);
            $batch->settle($this->rootsOf($adopter, $group));
            $batch->tick();
        }

        $batch->finish();

        return $adopted;
    }

    /**
     * The root-reference associations declared on $class itself, for the stream's
     * fetch join. A root is an STI/JOINED hierarchy more often than not, and Doctrine
     * cannot proxy a to-one target that has subclasses: it loads it with one find()
     * per row during hydration -- 1 + G queries for G groups. A plain root class is a
     * proxy that classify()'s first read initializes, the same 1 + G. Joined, the
     * stream is one query. A reference a subclass alone declares cannot be joined
     * from the hierarchy root's alias and keeps Doctrine's own loading.
     *
     * @param class-string $class
     *
     * @return list<string>
     */
    private function rootReferenceJoins(string $class): array
    {
        $metadata = $this->entityManager->getClassMetadata($class);
        $joins    = [];

        foreach ($this->rootReferences($class) as $reference) {
            if ($reference['class'] === $class && $metadata->hasAssociation($reference['property'])) {
                $joins[] = $reference['property'];
            }
        }

        return $joins;
    }

    /**
     * The distinct roots the rows of $group point at (none for a NEW group).
     *
     * @param non-empty-list<TranslatableInterface> $group
     *
     * @return list<TranslationRootInterface>
     */
    private function rootsOf(RootAdopterInterface $adopter, array $group): array
    {
        /** @var array<int, TranslationRootInterface> $roots */
        $roots = [];

        foreach ($group as $row) {
            $root = $adopter->getRoot($row);

            if (null !== $root) {
                $roots[spl_object_id($root)] = $root;
            }
        }

        return array_values($roots);
    }

    /**
     * @param non-empty-list<TranslatableInterface> $group
     */
    private function adoptNewGroup(RootAdopterInterface $adopter, array $group): TranslationRootInterface
    {
        $tuuid    = $group[0]->getTuuid();
        $root     = $adopter->createRootFor($group);
        $expected = $adopter->rootClassFor($group[0]);

        if ($root->hasTuuid()) {
            throw RootAdoptionException::forMintedRoot($root::class, (string) $tuuid);
        }

        if (!$root instanceof $expected) {
            throw RootAdoptionException::forWrongRootClass($expected, $root::class, (string) $tuuid);
        }

        $root->adoptTuuid($tuuid);
        $this->entityManager->persist($root);

        foreach ($group as $row) {
            $adopter->attach($row, $root);
        }

        return $root;
    }

    /**
     * @param non-empty-list<TranslatableInterface> $group
     */
    private function healPartialGroup(RootAdopterInterface $adopter, array $group): void
    {
        $root = null;

        foreach ($group as $row) {
            $root ??= $adopter->getRoot($row);
        }

        \assert($root instanceof TranslationRootInterface);

        foreach ($group as $row) {
            if (null === $adopter->getRoot($row)) {
                $adopter->attach($row, $root);
            }
        }
    }

    /**
     * The whole-group classification -- see the class docblock for the six kinds.
     * Tuuids compare by STRING, never by object identity: identity is not observable
     * across a cold process, and this is what --check runs in. Distinct roots inside
     * one group ARE told apart by identity (spl_object_id) -- within one UnitOfWork
     * the identity map makes that equivalent to comparing their ids, and the rows of
     * a group were hydrated by the same stream.
     *
     * @param non-empty-list<TranslatableInterface> $group
     *
     * @return Classification
     */
    private function classify(RootAdopterInterface $adopter, array $group): array
    {
        $tuuid   = (string) $group[0]->getTuuid();
        $locales = implode(', ', array_map(static fn (TranslatableInterface $row): string => $row->getLocale() ?? 'none', $group));

        /** @var array<string, true> $rootClasses */
        $rootClasses = [];
        /** @var array<string, true> $keys */
        $keys = [];
        /** @var array<int, TranslationRootInterface> $roots */
        $roots   = [];
        $missing = 0;
        /** @var list<string> $drift */
        $drift = [];

        foreach ($group as $row) {
            $rootClass                          = $adopter->rootClassFor($row);
            $rootClasses[$rootClass]            = true;
            $keys[$adopter->coherenceKey($row)] = true;

            $root = $adopter->getRoot($row);

            if (null === $root) {
                ++$missing;

                continue;
            }

            $roots[spl_object_id($root)] = $root;

            if (!$root->hasTuuid() || (string) $root->getTuuid() !== (string) $row->getTuuid()) {
                $drift[] = \sprintf('%s: root tuuid %s', $row->getLocale() ?? 'none', $root->hasTuuid() ? (string) $root->getTuuid() : 'none');
            }

            if (!$root instanceof $rootClass) {
                $drift[] = \sprintf('%s: root is %s, rows imply %s', $row->getLocale() ?? 'none', $root::class, $rootClass);
            }
        }

        $result = static fn (string $kind, string $detail): array => ['kind' => $kind, 'tuuid' => $tuuid, 'locales' => $locales, 'detail' => $detail];

        if (\count($rootClasses) > 1 || \count($keys) > 1) {
            ksort($rootClasses);
            ksort($keys);

            return $result(self::KIND_MISMATCHED, \sprintf('root classes: %s; coherence keys: %s', implode(', ', array_keys($rootClasses)), implode(', ', array_map(static fn (string $k): string => '"'.$k.'"', array_keys($keys)))));
        }

        if ([] === $roots) {
            return $result(self::KIND_NEW, \sprintf('%d row(s) without a root', $missing));
        }

        if (\count($roots) > 1) {
            return $result(self::KIND_AMBIGUOUS, \sprintf('%d distinct roots', \count($roots)));
        }

        if ([] !== $drift) {
            return $result(self::KIND_DRIFT, implode('; ', $drift));
        }

        if ($missing > 0) {
            return $result(self::KIND_PARTIAL, \sprintf('%d of %d row(s) attached', \count($group) - $missing, \count($group)));
        }

        return $result(self::KIND_COMPLETE, '');
    }

    /**
     * Roots that no row of $class (any leaf, any locale) references -- one NOT EXISTS
     * query per root reference the hierarchy declares. Returns whether anything is
     * non-zero or errored.
     *
     * @param class-string $class
     */
    private function reportRootsWithoutRows(SymfonyStyle $io, string $class): bool
    {
        $rows   = [];
        $failed = false;

        foreach ($this->rootReferences($class) as $reference) {
            try {
                $count  = $this->checks->countRootsWithoutRows($reference['root'], $reference['class'], $reference['property']);
                $rows[] = [$reference['root'], \sprintf('%s::$%s', $reference['class'], $reference['property']), (string) $count];
                $failed = $failed || $count > 0;
            } catch (\Throwable $e) {
                $rows[] = [$reference['root'], \sprintf('%s::$%s', $reference['class'], $reference['property']), 'ERROR ('.$e::class.')'];
                $failed = true;
            }
        }

        $io->table(['Root', 'Referenced by', 'Roots without rows'], $rows);

        return $failed;
    }

    /**
     * Every registered orphan counter, printed at 0 too. Returns whether anything is
     * non-zero or errored.
     */
    private function reportOrphanCounters(SymfonyStyle $io): bool
    {
        $counters = $this->checks->counters();

        if ([] === $counters) {
            $io->writeln('No tmi_translation.tuuid_orphan_counter registered.');

            return false;
        }

        $rows   = [];
        $failed = false;

        foreach ($counters as $counter) {
            try {
                $count  = $counter->countOrphans();
                $rows[] = [$counter->getName(), (string) $count, 0 === $count ? 'OK' : 'ORPHANS'];
                $failed = $failed || $count > 0;
            } catch (\Throwable $e) {
                $rows[] = [$counter->getName(), 'ERROR', $e::class];
                $failed = true;
            }
        }

        $io->table(['Counter', 'Orphans', 'Status'], $rows);

        return $failed;
    }

    /**
     * The root references declared anywhere in $class's hierarchy -- the root itself
     * and every concrete subclass -- deduplicated by declaring class and property, with
     * the association's mapped target as the root class to query.
     *
     * @param class-string $class
     *
     * @return list<RootReference>
     */
    private function rootReferences(string $class): array
    {
        $metadata = $this->entityManager->getClassMetadata($class);

        /** @var array<string, RootReference> $references */
        $references = [];

        foreach ([$metadata->getName(), ...$metadata->subClasses] as $entityClass) {
            $entityMetadata = $this->entityManager->getClassMetadata($entityClass);

            foreach (ReflectionHelper::getHierarchyProperties($entityMetadata->getReflectionClass()) as $property) {
                if (!$this->attributeHelper->isTranslationRootReference($property)) {
                    continue;
                }

                $key = $property->class.'::'.$property->name;

                if (isset($references[$key])) {
                    continue;
                }

                /** @var class-string $root */
                $root = $entityMetadata->getAssociationTargetClass($property->name);

                $references[$key] = ['class' => $entityMetadata->getName(), 'property' => $property->name, 'root' => $root];
            }
        }

        return array_values($references);
    }
}
