<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\Root;

use PHPUnit\Framework\Attributes\CoversClass;
use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\Root\RootCheckAggregator;
use Tmi\TranslationBundle\Doctrine\Root\TuuidOrphanCounterInterface;
use Tmi\TranslationBundle\Fixtures\Entity\Root\Estate;
use Tmi\TranslationBundle\Fixtures\Entity\Root\EstateA;
use Tmi\TranslationBundle\Fixtures\Entity\Root\Listing;
use Tmi\TranslationBundle\Fixtures\Entity\Root\ListingA;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * Roots without rows, counted with NOT EXISTS across every locale -- and the two
 * traps that rule exists for, each pinned as a negative fixture.
 */
#[CoversClass(RootCheckAggregator::class)]
final class RootCheckAggregatorTest extends IntegrationTestCase
{
    public function testCountersAreKeptInRegistrationOrder(): void
    {
        $aggregator = $this->aggregator();
        $first      = $this->counter('a', 0);
        $second     = $this->counter('b', 0);

        self::assertSame([], $aggregator->counters());

        $aggregator->addCounter($first);
        $aggregator->addCounter($second);

        self::assertSame([$first, $second], $aggregator->counters());
    }

    public function testCountsTheRootsNoRowReferences(): void
    {
        $referenced = $this->root();
        $this->root();
        $this->root();

        $this->entityManager()->persist(new EstateA()->setTuuid($referenced->getTuuid())->setLocale('en_US')->setListing($referenced));
        $this->entityManager()->flush();

        self::assertSame(2, $this->aggregator()->countRootsWithoutRows(Listing::class, Estate::class, 'listing'));
    }

    /**
     * The NOT IN trap: during the migration window rows exist with a NULL FK. `NOT IN
     * (subquery)` then evaluates to UNKNOWN for every root and reports zero orphans
     * regardless of the truth -- pinned here as the wrong answer, next to the right one.
     */
    public function testANullForeignKeyInTheTableDoesNotHideAnOrphanedRoot(): void
    {
        $referenced = $this->root();
        $this->root();

        $this->entityManager()->persist(new EstateA()->setTuuid($referenced->getTuuid())->setLocale('en_US')->setListing($referenced));
        $this->entityManager()->persist(new EstateA()->setTuuid(Tuuid::generate())->setLocale('en_US')); // NULL FK, not yet adopted
        $this->entityManager()->flush();

        self::assertSame(1, $this->aggregator()->countRootsWithoutRows(Listing::class, Estate::class, 'listing'));

        $notIn = $this->entityManager()->createQuery(sprintf(
            'SELECT COUNT(r) FROM %s r WHERE r.id NOT IN (SELECT IDENTITY(t.listing) FROM %s t)',
            Listing::class,
            Estate::class,
        ))->getSingleScalarResult();

        self::assertSame(0, (int) $notIn, 'NOT IN over a subquery containing NULL is the wrong answer -- this is why the rule exists');
    }

    /**
     * The locale-filter trap: a root whose only rows are in another locale is NOT
     * orphaned, but a filtered subquery would not see those rows.
     */
    public function testARootReferencedOnlyFromAnotherLocaleIsNotAnOrphan(): void
    {
        $referenced = $this->root();

        $this->entityManager()->persist(new EstateA()->setTuuid($referenced->getTuuid())->setLocale('de_DE')->setListing($referenced));
        $this->entityManager()->flush();

        $filters = $this->entityManager()->getFilters();
        self::assertTrue($filters->isEnabled(LocaleFilter::NAME));
        $filters->getFilter(LocaleFilter::NAME)->setParameter('locale', 'en_US');

        self::assertSame(0, $this->aggregator()->countRootsWithoutRows(Listing::class, Estate::class, 'listing'));
        self::assertTrue($filters->isEnabled(LocaleFilter::NAME), 'the filter is restored after the query');
    }

    public function testAnEmptyTableHasNoOrphanedRoots(): void
    {
        self::assertSame(0, $this->aggregator()->countRootsWithoutRows(Listing::class, Estate::class, 'listing'));
    }

    private function aggregator(): RootCheckAggregator
    {
        return new RootCheckAggregator($this->entityManager(), new LocaleVariantFinder($this->entityManager()));
    }

    private function root(): ListingA
    {
        $root = new ListingA();
        $root->mintTuuid();
        $this->entityManager()->persist($root);

        return $root;
    }

    private function counter(string $name, int $count): TuuidOrphanCounterInterface
    {
        return new class($name, $count) implements TuuidOrphanCounterInterface {
            public function __construct(private readonly string $name, private readonly int $count)
            {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function countOrphans(): int
            {
                return $this->count;
            }
        };
    }
}
