<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tmi\TranslationBundle\Doctrine\TranslatableEntityLocator;

/**
 * Scans every translatable entity table for broken Tuuid linkage.
 *
 * Reports three anomaly classes and exits non-zero when any are found, so it
 * can run as a post-migration / CI integrity gate:
 *
 *  1. standalone — a Tuuid carried by a single locale row (no sibling);
 *  2. incomplete — a Tuuid with fewer locale rows than configured locales;
 *  3. duplicate  — more than one row sharing the same (tuuid, locale) pair.
 */
#[AsCommand(
    name: 'tmi:translation:doctor',
    description: 'Detect broken translation linkage across translatable entities.',
)]
final class TranslationDoctorCommand extends Command
{
    private const string LOCALE_FILTER = 'tmi_translation_locale_filter';

    /**
     * @param list<string> $locales
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatableEntityLocator $locator,
        private readonly array $locales,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('TMI Translation Doctor');

        $classes = $this->locator->locate();

        if ([] === $classes) {
            $io->warning('No translatable entities found.');

            return Command::SUCCESS;
        }

        $expectedLocaleCount = count($this->locales);
        $anomalies           = 0;

        $filters    = $this->entityManager->getFilters();
        $wasEnabled = $filters->has(self::LOCALE_FILTER) && $filters->isEnabled(self::LOCALE_FILTER);

        if ($wasEnabled) {
            $filters->disable(self::LOCALE_FILTER);
        }

        try {
            foreach ($classes as $class) {
                $anomalies += $this->inspect($io, $class, $expectedLocaleCount);
            }
        } finally {
            if ($wasEnabled) {
                $filters->enable(self::LOCALE_FILTER);
            }
        }

        if ($anomalies > 0) {
            $io->error(sprintf('%d translation linkage anomaly/anomalies detected.', $anomalies));

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
    private function inspect(SymfonyStyle $io, string $class, int $expectedLocaleCount): int
    {
        $io->section($class);

        $metadata = $this->entityManager->getClassMetadata($class);
        $idField  = $metadata->getIdentifierFieldNames()[0] ?? 'id';

        /** @var list<array{tuuid: mixed, locale: mixed, cnt: mixed}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('t.tuuid AS tuuid', 't.locale AS locale', sprintf('COUNT(t.%s) AS cnt', $idField))
            ->from($class, 't')
            ->groupBy('t.tuuid')
            ->addGroupBy('t.locale')
            ->getQuery()
            ->getResult();

        /** @var array<string, array<string, int>> $byTuuid */
        $byTuuid = [];

        foreach ($rows as $row) {
            $byTuuid[self::asString($row['tuuid'])][self::asString($row['locale'])] = self::asInt($row['cnt']);
        }

        /** @var list<array{0: string, 1: string}> $standalone */
        $standalone = [];
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

            $localeCount = count($localeCounts);

            if ($localeCount < $expectedLocaleCount) {
                if (1 === $localeCount) {
                    $standalone[] = [$tuuid, array_key_first($localeCounts)];
                } else {
                    $incomplete[] = [$tuuid, $localeCount, implode(', ', array_keys($localeCounts))];
                }
            }
        }

        $total = count($standalone) + count($incomplete) + count($duplicates);

        if (0 === $total) {
            $io->writeln('<info>OK</info> — no anomalies.');

            return 0;
        }

        if ([] !== $standalone) {
            $io->writeln(sprintf('<comment>Standalone translations (%d):</comment>', count($standalone)));
            $io->table(['Tuuid', 'Only locale'], $standalone);
        }

        if ([] !== $incomplete) {
            $io->writeln(sprintf('<comment>Incomplete translations (%d):</comment>', count($incomplete)));
            $io->table(
                ['Tuuid', 'Locale rows', 'Locales present'],
                array_map(
                    static fn (array $r): array => [$r[0], (string) $r[1], $r[2]],
                    $incomplete,
                ),
            );
        }

        if ([] !== $duplicates) {
            $io->writeln(sprintf('<comment>Duplicate (tuuid, locale) pairs (%d):</comment>', count($duplicates)));
            $io->table(
                ['Tuuid', 'Locale', 'Rows'],
                array_map(
                    static fn (array $r): array => [$r[0], $r[1], (string) $r[2]],
                    $duplicates,
                ),
            );
        }

        return $total;
    }

    private static function asString(mixed $value): string
    {
        // tuuid hydrates as a Tuuid value object, locale as a nullable string.
        assert(null === $value || is_string($value) || $value instanceof \Stringable);

        return (string) $value;
    }

    private static function asInt(mixed $value): int
    {
        // COUNT() hydrates as an int or a numeric string depending on the platform.
        assert(is_numeric($value));

        return (int) $value;
    }
}
