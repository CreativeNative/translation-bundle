<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Performance;

use Symfony\Component\Console\Tester\CommandTester;
use Tmi\TranslationBundle\Command\TranslationDoctorCommand;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\TranslatableEntityLocator;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalParent;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\Test\Support\QueryCounter;
use Tmi\TranslationBundle\Translation\LocaleCompletenessResolver;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * Turns the performance claims WP17's README table makes into assertions: each
 * test here is the one a README/llms.md number is allowed to cite (Leitplanke
 * 7 -- no performance number without a test enforcing it).
 *
 * Counts come from {@see QueryCounter}, the PSR-3 logger wired into
 * TestKernel behind DBAL's own logging middleware -- one debug log per
 * executed statement or query, transaction control excluded (see its
 * docblock). Every assertion below is exact (assertSame), not a ceiling.
 */
final class QueryBudgetTest extends IntegrationTestCase
{
    public function testFindTranslatableEntityUnderActiveFilterIsOneQuery(): void
    {
        $entity = new Scalar()->setLocale('en_US')->setTitle('EN');
        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();
        $id = $entity->getId();
        $this->entityManager()->clear();

        $this->counter()->reset();
        $found = $this->entityManager()->find(Scalar::class, $id);
        self::assertNotNull($found);
        self::assertSame(1, $this->counter()->count());
    }

    /**
     * translate() of an entity whose target-locale variant already exists:
     * EntityTranslator::processTranslation() finds it via its own preload()
     * call and returns straight from the cache, never reaching the handler
     * chain (and its own, separate existing-variant lookup) at all.
     */
    public function testTranslateEntityWithExistingVariantIsOneQueryAndNoInserts(): void
    {
        $tuuid = Tuuid::generate();
        $en    = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de    = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $this->entityManager()->persist($en);
        $this->entityManager()->persist($de);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $this->counter()->reset();
        $result = $this->translator()->translate($en, 'de_DE');

        self::assertSame('de_DE', $result->getLocale());
        self::assertSame(1, $this->counter()->count());

        // The identity check above already proves no new row was created for
        // this Tuuid; confirm no INSERT reached the database either.
        self::assertSame(2, $this->countScalarRows());
    }

    /**
     * The mechanism preload() exists for: K entities of the same class, each
     * already translated, cost K individual lookup queries when translate()d
     * one at a time -- each one's own internal preload() call is a separate
     * single-Tuuid query, since nothing warmed its cache entry yet. Calling
     * preload() once with the whole batch first turns that into a single
     * query for the class (LocaleVariantFinder::findLocaleVariantsBatch()),
     * after which every translate() in the loop is a pure cache hit.
     */
    public function testPreloadCollapsesPerEntityLookupsIntoOneQueryPerClass(): void
    {
        $baseline = $this->createTranslatedScalarPairs(3);

        $this->counter()->reset();
        foreach ($baseline as $en) {
            $this->translator()->translate($en, 'de_DE');
        }
        self::assertSame(3, $this->counter()->count(), 'one lookup query per entity, without preload()');

        $preloaded = $this->createTranslatedScalarPairs(3);

        $this->counter()->reset();
        $this->translator()->preload($preloaded, 'de_DE');
        foreach ($preloaded as $en) {
            $this->translator()->translate($en, 'de_DE');
        }
        self::assertSame(1, $this->counter()->count(), 'one batched query for the whole class, with preload()');
    }

    /**
     * A parent entity (no existing target-locale variant, so it must be
     * created) with K already-translated children reachable through a
     * bidirectional OneToMany: each child is itself a top-level-shaped
     * translate() call (BidirectionalOneToManyHandler recurses through
     * EntityTranslator::processTranslation(), not around it), so an
     * already-existing child variant resolves from the shared cache in
     * exactly one query each, same as testTranslateEntityWithExistingVariantIsOneQueryAndNoInserts().
     * The parent itself costs two: its own preload() lookup (miss) plus
     * TranslatableEntityHandler's own existing-variant lookup once the
     * miss falls through to the handler chain -- that duplicate check is
     * pre-existing (WP3/WP6), not something this WP introduces or changes.
     */
    public function testTranslateWithExistingChildVariantsCostsOneQueryPerChild(): void
    {
        $parentTuuid = Tuuid::generate();
        $parent      = new TranslatableOneToManyBidirectionalParent()->setTuuid($parentTuuid)->setLocale('en_US')->setTitle('Parent EN');

        $childCount = 3;
        for ($i = 0; $i < $childCount; ++$i) {
            $childTuuid = Tuuid::generate();
            $childEn    = new TranslatableManyToOneBidirectionalChild()->setTuuid($childTuuid)->setLocale('en_US')->setTitle('Child EN '.$i);
            $childDe    = new TranslatableManyToOneBidirectionalChild()->setTuuid($childTuuid)->setLocale('de_DE')->setTitle('Child DE '.$i);
            $childEn->setParentSimple($parent);
            $parent->getSimpleChildren()->add($childEn);
            $this->entityManager()->persist($childEn);
            $this->entityManager()->persist($childDe);
        }
        $this->entityManager()->persist($parent);
        $this->entityManager()->flush();

        $this->counter()->reset();
        $translated = $this->translator()->translate($parent, 'de_DE');
        self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $translated);
        self::assertSame('de_DE', $translated->getLocale());
        self::assertCount($childCount, $translated->getSimpleChildren());

        // 1 (parent's own preload(), miss) + 1 (TranslatableEntityHandler's
        // own existing-variant lookup once the miss falls through to it) +
        // $childCount (one cache-satisfied lookup per already-translated
        // child) -- see class docblock above.
        self::assertSame(2 + $childCount, $this->counter()->count());
    }

    public function testResolveBatchOfManyTuuidsIsOneQuery(): void
    {
        $tuuids = [];
        for ($i = 0; $i < 100; ++$i) {
            $tuuid    = Tuuid::generate();
            $tuuids[] = $tuuid;
            $this->entityManager()->persist(new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN '.$i));
        }
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $resolver = new LocaleCompletenessResolver(
            $this->entityManager(),
            $this->attributeHelper(),
            'en_US',
            ['en_US', 'de_DE', 'it_IT'],
            new LocaleVariantFinder($this->entityManager()),
        );

        $this->counter()->reset();
        $result = $resolver->resolveBatch(Scalar::class, $tuuids);
        self::assertCount(100, $result);
        self::assertSame(1, $this->counter()->count());
    }

    public function testFindAllLocaleVariantsBatchIsOneQuery(): void
    {
        $tuuids = [];
        for ($i = 0; $i < 5; ++$i) {
            $tuuid    = Tuuid::generate();
            $tuuids[] = $tuuid;
            $this->entityManager()->persist(new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN '.$i));
            $this->entityManager()->persist(new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE '.$i));
        }
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $finder = new LocaleVariantFinder($this->entityManager());

        $this->counter()->reset();
        $result = $finder->findAllLocaleVariantsBatch(Scalar::class, $tuuids);
        self::assertCount(5, $result);
        self::assertSame(1, $this->counter()->count());
    }

    public function testDoctorQueriesPerRootClass(): void
    {
        $this->entityManager()->persist(new Scalar()->setLocale('en_US')->setTitle('Lonely'));
        $this->entityManager()->flush();

        $command = new TranslationDoctorCommand(
            $this->entityManager(),
            new TranslatableEntityLocator($this->entityManager()),
            ['en_US', 'de_DE', 'it_IT'],
        );
        $tester = new CommandTester($command);

        // --entity restricts the scan to exactly one root class, so the
        // count below is the per-class budget itself, independent of how
        // many translatable fixtures the test suite happens to declare: one
        // grouped query covering the standalone/incomplete/duplicate anomaly
        // classes together (TranslationDoctorCommand::inspect(), a single
        // GROUP BY tuuid, locale) plus inspectNullTuuid()'s own query.
        $this->counter()->reset();
        $tester->execute(['--entity' => Scalar::class]);
        self::assertSame(2, $this->counter()->count());
    }

    /**
     * The recommended import shape: preload() once for the whole batch,
     * then getOrTranslate() + flush() per entity. Every Tuuid here is brand
     * new (no existing de_DE variant), so the upfront preload() finds
     * nothing to warm the cache with -- each entity's own translate() still
     * pays its usual create-path cost (its own preload() miss, then
     * TranslatableEntityHandler's own existing-variant miss once the
     * handler chain runs, same two-query pattern as the parent's miss path
     * in testTranslateWithExistingChildVariantsCostsOneQueryPerChild()) on
     * top of the N inserts flush() issues. preload() only pays off here
     * as a duplicate-import guard, not as a lookup eliminator: a re-run of
     * the same import would find every Tuuid already translated and skip
     * straight to the identity cache hit, at one query total for the whole
     * batch (see testPreloadCollapsesPerEntityLookupsIntoOneQueryPerClass()).
     */
    public function testImportWithPreloadCostsOneLookupPlusNInserts(): void
    {
        $entities = [];
        $n        = 4;
        for ($i = 0; $i < $n; ++$i) {
            $entities[] = new Scalar()->setTuuid(Tuuid::generate())->setLocale('en_US')->setTitle('Import '.$i);
        }

        $this->counter()->reset();
        $this->translator()->preload($entities, 'de_DE');

        foreach ($entities as $entity) {
            $this->translator()->getOrTranslate($entity, 'de_DE');
        }
        $this->entityManager()->flush();

        // 1 (the upfront batched preload(), a miss for every Tuuid) + N
        // (each entity's own preload() miss) + N (TranslatableEntityHandler's
        // own miss once the handler chain runs) + N (INSERT on flush).
        self::assertSame(1 + 3 * $n, $this->counter()->count());
    }

    private function counter(): QueryCounter
    {
        $counter = self::getContainer()->get(QueryCounter::class);
        self::assertInstanceOf(QueryCounter::class, $counter);

        return $counter;
    }

    private function countScalarRows(): int
    {
        /** @var int|string $count */
        $count = $this->entityManager()->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Scalar::class, 't')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    /**
     * @return list<Scalar>
     */
    private function createTranslatedScalarPairs(int $count): array
    {
        $entities = [];

        for ($i = 0; $i < $count; ++$i) {
            $tuuid = Tuuid::generate();
            $en    = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN '.$i);
            $de    = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE '.$i);
            $this->entityManager()->persist($en);
            $this->entityManager()->persist($de);
            $entities[] = $en;
        }
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        return $entities;
    }
}
