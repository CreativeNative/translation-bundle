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
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\TranslatableEntityLocator;

/**
 * Scans every translatable entity table for broken Tuuid linkage.
 *
 * Reports five classes of finding. Three are anomalies -- linkage that is
 * broken -- and fail the run, so it can gate a post-migration check or CI:
 *
 *  1. orphan      — a Tuuid whose only row carries a NON-default locale: a
 *     translation without the row it was translated from (what
 *     TranslatableEventSubscriber warns about at flush time, seen at rest);
 *  2. duplicate   — more than one row sharing the same (tuuid, locale) pair;
 *  3. null-tuuid  — a row whose tuuid column is NULL, e.g. from a raw insert
 *     that bypassed the entity layer (the column is NOT NULL as of v4, so a
 *     normal persist() can no longer produce one). Excluded from the query
 *     behind the other classes (see inspectNullTuuid()) so distinct NULL rows
 *     are never folded into one fake shared group, and reported separately by
 *     id instead.
 *
 * Two are informational -- a translation that has not happened yet is the
 * normal state of a record in an application that translates lazily or on
 * demand, not a defect -- listed, but never counted unless `--strict` asks:
 *
 *  4. untranslated — a Tuuid whose only row carries the DEFAULT locale;
 *  5. incomplete   — a Tuuid with two or more locale rows but fewer than the
 *     configured locales.
 *
 * `--strict` counts all five and restores a gate that fails until every
 * record exists in every enabled locale.
 */
#[AsCommand(
    name: 'tmi:translation:doctor',
    description: 'Detect broken translation linkage across translatable entities.',
)]
final class TranslationDoctorCommand extends Command
{
    /**
     * @param list<string> $locales
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatableEntityLocator $locator,
        private readonly LocaleVariantFinder $finder,
        private readonly array $locales,
        private readonly string $defaultLocale,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'Restrict the scan to a single entity class.')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Also fail on untranslated (default-locale-only) and incomplete records, not only on broken linkage.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('TMI Translation Doctor');

        $classes = $this->locator->locate();
        $strict  = true === $input->getOption('strict');

        /** @var string|null $only */
        $only = $input->getOption('entity');

        if (null !== $only) {
            // Checked against Doctrine's metadata, not membership in locate()'s list:
            // the locator names only the root of each inheritance hierarchy, and
            // --entity must still accept a concrete subclass.
            if (!$this->locator->isTranslatableEntity($only)) {
                $io->error(\sprintf('"%s" is not a known translatable entity.', $only));

                return Command::FAILURE;
            }
            $classes = [$only];
        }

        if ([] === $classes) {
            $io->warning('No translatable entities found.');

            return Command::SUCCESS;
        }

        $expectedLocaleCount = \count($this->locales);

        // The scan must see every locale row; the finder suspends the locale
        // filter for the duration and restores it afterwards, whatever happens.
        $anomalies = $this->finder->withoutLocaleFilter(function () use ($io, $classes, $expectedLocaleCount, $strict): int {
            $anomalies = 0;

            foreach ($classes as $class) {
                $anomalies += $this->inspect($io, $class, $expectedLocaleCount, $strict);
            }

            return $anomalies;
        });

        if ($anomalies > 0) {
            $io->error(\sprintf('%d translation linkage anomaly/anomalies detected.', $anomalies));

            return Command::FAILURE;
        }

        $io->success('All translatable entities are correctly linked.');

        return Command::SUCCESS;
    }

    /**
     * @param class-string $class
     *
     * @return int Number of anomalies found for this entity class
     */
    private function inspect(SymfonyStyle $io, string $class, int $expectedLocaleCount, bool $strict): int
    {
        $io->section($class);

        $metadata = $this->entityManager->getClassMetadata($class);
        $idField  = $metadata->getIdentifierFieldNames()[0] ?? 'id';

        /** @var list<array{tuuid: mixed, locale: mixed, cnt: mixed}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('t.tuuid AS tuuid', 't.locale AS locale', \sprintf('COUNT(t.%s) AS cnt', $idField))
            ->from($class, 't')
            ->where('t.tuuid IS NOT NULL')
            ->groupBy('t.tuuid')
            ->addGroupBy('t.locale')
            ->getQuery()
            ->getResult();

        /** @var array<string, array<string, int>> $byTuuid */
        $byTuuid = [];

        foreach ($rows as $row) {
            $byTuuid[self::asString($row['tuuid'])][self::asString($row['locale'])] = self::asInt($row['cnt']);
        }

        /** @var list<array{0: string, 1: string}> $untranslated */
        $untranslated = [];
        /** @var list<array{0: string, 1: string}> $orphans */
        $orphans = [];
        /** @var list<array{0: string, 1: int, 2: string}> $incomplete */
        $incomplete = [];
        /** @var list<array{0: string, 1: string, 2: int}> $duplicates */
        $duplicates = [];

        foreach ($byTuuid as $tuuid => $localeCounts) {
            foreach ($localeCounts as $locale => $cnt) {
                if ($cnt > 1) {
                    $duplicates[] = [$tuuid, $locale, $cnt];
                }
            }

            $localeCount = \count($localeCounts);

            if ($localeCount >= $expectedLocaleCount) {
                continue;
            }

            if (1 !== $localeCount) {
                $incomplete[] = [$tuuid, $localeCount, implode(', ', array_keys($localeCounts))];

                continue;
            }

            // One row only: pending translation when it is the default locale's,
            // a translation without its source when it is any other locale's.
            $onlyLocale = array_key_first($localeCounts);

            if ($onlyLocale === $this->defaultLocale) {
                $untranslated[] = [$tuuid, $onlyLocale];
            } else {
                $orphans[] = [$tuuid, $onlyLocale];
            }
        }

        $nullTuuid = $this->inspectNullTuuid($class, $idField);

        $anomalies     = \count($orphans)      + \count($duplicates) + \count($nullTuuid);
        $informational = \count($untranslated) + \count($incomplete);
        $total         = $strict ? $anomalies  + $informational : $anomalies;

        if (0 === $anomalies + $informational) {
            $io->writeln('<info>OK</info> — no anomalies.');

            return 0;
        }

        if ([] !== $untranslated) {
            $io->writeln(\sprintf('<comment>Untranslated (default locale only) (%d):</comment>', \count($untranslated)));
            $io->table(['Tuuid', 'Only locale'], $untranslated);
        }

        if ([] !== $orphans) {
            $io->writeln(\sprintf('<comment>Orphan translations (non-default locale only) (%d):</comment>', \count($orphans)));
            $io->table(['Tuuid', 'Only locale'], $orphans);
        }

        if ([] !== $incomplete) {
            $io->writeln(\sprintf('<comment>Incomplete translations (%d):</comment>', \count($incomplete)));
            $io->table(
                ['Tuuid', 'Locale rows', 'Locales present'],
                array_map(
                    static fn (array $r): array => [$r[0], (string) $r[1], $r[2]],
                    $incomplete,
                ),
            );
        }

        if ([] !== $duplicates) {
            $io->writeln(\sprintf('<comment>Duplicate (tuuid, locale) pairs (%d):</comment>', \count($duplicates)));
            $io->table(
                ['Tuuid', 'Locale', 'Rows'],
                array_map(
                    static fn (array $r): array => [$r[0], $r[1], (string) $r[2]],
                    $duplicates,
                ),
            );
        }

        if ([] !== $nullTuuid) {
            $io->writeln(\sprintf('<comment>NULL-tuuid rows (%d):</comment>', \count($nullTuuid)));
            $io->table(['Id', 'Locale'], $nullTuuid);
        }

        if (0 === $total) {
            $io->writeln('<info>OK</info> — no linkage anomalies; untranslated and incomplete records are listed for information (pass --strict to count them).');
        }

        return $total;
    }

    /**
     * Rows whose tuuid column is a literal database NULL — the grouped query
     * above deliberately excludes them (its `IS NOT NULL`) instead of folding
     * them into that grouping, because hydrating `t.tuuid` for a NULL value
     * calls TuuidType::convertToPHPValue(), which now returns null rather
     * than throwing (see TuuidType). Every NULL-tuuid row would then hydrate
     * under the same empty-string group key, collapsing unrelated broken
     * rows into one fake shared "tuuid" instead of reporting each. Selecting
     * only the id and locale here avoids that hydration for the very column
     * this check exists to flag as missing.
     *
     * @param class-string $class
     *
     * @return list<array{0: string, 1: string}>
     */
    private function inspectNullTuuid(string $class, string $idField): array
    {
        /** @var list<array{id: mixed, locale: mixed}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(\sprintf('t.%s AS id', $idField), 't.locale AS locale')
            ->from($class, 't')
            ->where('t.tuuid IS NULL')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): array => [self::asString($row['id']), self::asString($row['locale'])],
            $rows,
        );
    }

    private static function asString(mixed $value): string
    {
        // tuuid hydrates as a Tuuid value object, locale as a nullable string,
        // an id as an int or a string depending on the entity's identifier type.
        \assert(null === $value || \is_scalar($value) || $value instanceof \Stringable);

        return (string) $value;
    }

    private static function asInt(mixed $value): int
    {
        // COUNT() hydrates as an int or a numeric string depending on the platform.
        \assert(is_numeric($value));

        return (int) $value;
    }
}
