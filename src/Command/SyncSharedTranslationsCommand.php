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

/**
 * Retroactively propagates #[SharedAmongstTranslations] values across all
 * locale variants of each Tuuid.
 *
 * Shared values are copied source → translation only at translate() time, so
 * data created in one locale and translated later — or edited after the fact —
 * keeps the shared value on the source row alone. This command back-fills the
 * siblings from the canonical (default-locale) row.
 */
#[AsCommand(
    name: 'tmi:translation:sync-shared',
    description: 'Propagate #[SharedAmongstTranslations] values across all locale variants.',
)]
final class SyncSharedTranslationsCommand extends Command
{
    private const string LOCALE_FILTER = 'tmi_translation_locale_filter';

    /** @var list<string> */
    private const array SYSTEM_PROPERTIES = ['tuuid', 'locale', 'translations'];

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
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'Restrict the sync to a single entity class.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = true === $input->getOption('dry-run');

        $io->title('TMI Translation — Sync Shared Values'.($dryRun ? ' (dry run)' : ''));

        $classes = $this->locator->locate();

        /** @var string|null $only */
        $only = $input->getOption('entity');

        if (null !== $only) {
            if (!in_array($only, $classes, true)) {
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

        try {
            foreach ($classes as $class) {
                $totalUpdated += $this->syncClass($io, $class, !$dryRun);
            }

            if (!$dryRun && $totalUpdated > 0) {
                $this->entityManager->flush();
            }
        } finally {
            if ($wasEnabled) {
                $filters->enable(self::LOCALE_FILTER);
            }
        }

        if (0 === $totalUpdated) {
            $io->success('All shared values are already in sync.');

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            $dryRun ? '%d translation(s) would be updated.' : '%d translation(s) updated.',
            $totalUpdated,
        ));

        return Command::SUCCESS;
    }

    /**
     * @param class-string $class
     *
     * @return int Number of sibling translations whose shared values changed
     */
    private function syncClass(SymfonyStyle $io, string $class, bool $apply): int
    {
        $io->section($class);

        $sharedProperties = $this->sharedProperties($class);

        if ([] === $sharedProperties) {
            $io->writeln('No #[SharedAmongstTranslations] properties — skipped.');

            return 0;
        }

        /** @var list<TranslatableInterface> $entities */
        $entities = $this->entityManager->getRepository($class)->findAll();

        /** @var array<string, list<TranslatableInterface>> $byTuuid */
        $byTuuid = [];

        foreach ($entities as $entity) {
            $byTuuid[(string) $entity->getTuuid()][] = $entity;
        }

        $updated = 0;

        foreach ($byTuuid as $variants) {
            $updated += $this->syncGroup($variants, $sharedProperties, $apply);
        }

        $io->writeln(0 === $updated
            ? '<info>OK</info> — already in sync.'
            : sprintf('<comment>%d translation(s) need updating.</comment>', $updated));

        return $updated;
    }

    /**
     * @param list<TranslatableInterface> $variants
     * @param list<\ReflectionProperty>   $sharedProperties
     */
    private function syncGroup(array $variants, array $sharedProperties, bool $apply): int
    {
        $source = $this->pickSource($variants);
        $count  = 0;

        foreach ($variants as $sibling) {
            if ($sibling === $source) {
                continue;
            }

            if ($this->syncSibling($source, $sibling, $sharedProperties, $apply)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param list<\ReflectionProperty> $sharedProperties
     */
    private function syncSibling(
        TranslatableInterface $source,
        TranslatableInterface $sibling,
        array $sharedProperties,
        bool $apply,
    ): bool {
        $changed = false;

        foreach ($sharedProperties as $property) {
            // Shared properties are mapped columns, so Doctrine has hydrated
            // them on both the source and the sibling.
            $value   = $property->getValue($source);
            $current = $property->getValue($sibling);

            if (self::valuesEqual($current, $value)) {
                continue;
            }

            $changed = true;

            if ($apply) {
                // Clone mutable objects so locale variants do not share a reference;
                // enums are immutable singletons and must not be cloned.
                $copy = is_object($value) && !$value instanceof \UnitEnum ? clone $value : $value;
                $property->setValue($sibling, $copy);
            }
        }

        return $changed;
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
     * @param class-string $class
     *
     * @return list<\ReflectionProperty>
     */
    private function sharedProperties(string $class): array
    {
        $metadata   = $this->entityManager->getClassMetadata($class);
        $reflection = $metadata->getReflectionClass();
        $shared     = [];

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();

            if (in_array($name, self::SYSTEM_PROPERTIES, true)) {
                continue;
            }

            if (!$metadata->hasField($name)) {
                continue;
            }

            if ($this->attributeHelper->isSharedAmongstTranslations($property)) {
                $shared[] = $property;
            }
        }

        return $shared;
    }
}
