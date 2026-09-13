<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Root;

use Doctrine\ORM\EntityManagerInterface;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;

/**
 * The CI-grade half of `tmi:translation:adopt-root --check` that needs no group
 * classification: roots without rows, counted generically by the bundle, and the
 * `tmi_translation.tuuid_orphan_counter` services the application contributes
 * (collected by TuuidOrphanCounterPass).
 */
final class RootCheckAggregator
{
    /** @var list<TuuidOrphanCounterInterface> */
    private array $counters = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LocaleVariantFinder $finder,
    ) {
    }

    public function addCounter(TuuidOrphanCounterInterface $counter): void
    {
        $this->counters[] = $counter;
    }

    /**
     * Every registered counter, in registration order.
     *
     * @return list<TuuidOrphanCounterInterface>
     */
    public function counters(): array
    {
        return $this->counters;
    }

    /**
     * Roots that no translation row references, in ONE query.
     *
     * `NOT EXISTS`, never `NOT IN (subquery)`: during the migration window the
     * subquery contains NULL foreign keys, and `NOT IN` then evaluates to UNKNOWN for
     * every row and reports zero orphans regardless of the truth. The translation rows
     * are counted across every locale -- with the locale filter active the subquery
     * would see only the current locale's rows and count a root whose rows are all in
     * another locale as orphaned.
     *
     * @param class-string $rootClass         the root entity (a SINGLE_TABLE root covers every leaf)
     * @param class-string $translatableClass the translation row entity that declares the reference
     * @param string       $rootProperty      the root reference property on $translatableClass
     */
    public function countRootsWithoutRows(string $rootClass, string $translatableClass, string $rootProperty): int
    {
        return $this->finder->withoutLocaleFilter(function () use ($rootClass, $translatableClass, $rootProperty): int {
            $count = $this->entityManager->createQuery(\sprintf(
                'SELECT COUNT(r) FROM %s r WHERE NOT EXISTS (SELECT t FROM %s t WHERE t.%s = r)',
                $rootClass,
                $translatableClass,
                $rootProperty,
            ))->getSingleScalarResult();

            return (int) $count;
        });
    }
}
