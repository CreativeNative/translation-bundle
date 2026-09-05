<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine;

use PHPUnit\Framework\Attributes\CoversClass;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\SharedDriftScanner;
use Tmi\TranslationBundle\Doctrine\SharedValueSynchronizer;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\EmbeddedSharedTranslatable;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti\StiBook;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti\StiRoot;
use Tmi\TranslationBundle\Fixtures\Entity\ReadonlyShared\ReadonlyShared;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\SharedDrift;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * The read side of `sync-shared --check` as a service: one SharedDrift per
 * (sibling row, property path), nothing written, memory bounded per group.
 */
#[CoversClass(SharedDriftScanner::class)]
final class SharedDriftScannerTest extends IntegrationTestCase
{
    public function testYieldsOneDriftPerDriftedSiblingRowAndPropertyPath(): void
    {
        $drifted = Tuuid::generate();
        $clean   = Tuuid::generate();

        $this->persistAll(
            new Scalar()->setTuuid($drifted)->setLocale('en_US')->setTitle('EN')->setShared('canonical'),
            new Scalar()->setTuuid($drifted)->setLocale('de_DE')->setTitle('DE')->setShared('stale'),
            new Scalar()->setTuuid($drifted)->setLocale('it_IT')->setTitle('IT')->setShared('stale'),
            new Scalar()->setTuuid($clean)->setLocale('en_US')->setTitle('EN')->setShared('same'),
            new Scalar()->setTuuid($clean)->setLocale('de_DE')->setTitle('DE')->setShared('same'),
        );

        $drifts = $this->scan(Scalar::class);

        self::assertCount(2, $drifts);

        foreach ($drifts as $drift) {
            self::assertSame(Scalar::class, $drift->entityClass());
            self::assertSame((string) $drifted, $drift->tuuid());
            self::assertSame('shared', $drift->propertyPath());
            self::assertSame('en_US', $drift->sourceLocale());
            self::assertFalse($drift->isReadonly());
        }

        self::assertEqualsCanonicalizing(['de_DE', 'it_IT'], array_map(static fn (SharedDrift $d): string => $d->locale(), $drifts));
    }

    public function testFlagsAReadonlyPropertyThatDrifted(): void
    {
        $tuuid = Tuuid::generate();

        $this->persistAll(
            new ReadonlyShared('SKU-EN')->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN')->setNote('canonical'),
            new ReadonlyShared('SKU-DE')->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setNote('stale'),
        );

        $drifts = $this->scan(ReadonlyShared::class);

        $byPath = [];
        foreach ($drifts as $drift) {
            $byPath[$drift->propertyPath()] = $drift->isReadonly();
        }

        self::assertSame(['note' => false, 'sku' => true], $byPath);
    }

    public function testWithoutADefaultLocaleRowTheFirstRowIsTheSource(): void
    {
        $tuuid = Tuuid::generate();

        $this->persistAll(
            new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setShared('German'),
            new Scalar()->setTuuid($tuuid)->setLocale('it_IT')->setTitle('IT')->setShared('Italian'),
        );

        $drifts = $this->scan(Scalar::class);

        self::assertCount(1, $drifts);
        self::assertNotSame($drifts[0]->sourceLocale(), $drifts[0]->locale());
        self::assertContains($drifts[0]->sourceLocale(), ['de_DE', 'it_IT']);
    }

    public function testNothingIsWrittenAndEveryGroupIsDetached(): void
    {
        $tuuid = Tuuid::generate();

        $deId = $this->persistAll(
            new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN')->setShared('canonical'),
            new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setShared('stale'),
        )[1];

        self::assertCount(1, $this->scan(Scalar::class));

        // Detached as it went: the identity map holds none of the rows the scan hydrated.
        $identityMap = $this->entityManager()->getUnitOfWork()->getIdentityMap();
        self::assertSame([], $identityMap[Scalar::class] ?? []);

        $this->entityManager()->flush();

        $reloaded = new LocaleVariantFinder($this->entityManager())->withoutLocaleFilter(fn (): Scalar|null => $this->entityManager()->find(Scalar::class, $deId));
        self::assertNotNull($reloaded);
        self::assertSame('stale', $reloaded->getShared(), 'A scan must never write.');
    }

    public function testScansAHierarchyThroughItsRootAndNamesTheConcreteClass(): void
    {
        $tuuid = Tuuid::generate();

        $en = new StiBook();
        $en->setIsbn('978-EN')->setTuuid($tuuid)->setLocale('en_US');
        $de = new StiBook();
        $de->setIsbn('978-STALE')->setTuuid($tuuid)->setLocale('de_DE');
        $this->persistAll($en, $de);

        $drifts = $this->scan(StiRoot::class);

        self::assertCount(1, $drifts);
        self::assertSame(StiBook::class, $drifts[0]->entityClass());
        self::assertSame('isbn', $drifts[0]->propertyPath());
    }

    public function testAnEmbeddedPathIsReportedInDotNotation(): void
    {
        $tuuid = Tuuid::generate();

        $en = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $en->getPropertyShared()->setReference('REF-1');
        $de = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $de->getPropertyShared()->setReference('REF-STALE');
        $this->persistAll($en, $de);

        $drifts = $this->scan(EmbeddedSharedTranslatable::class);

        self::assertCount(1, $drifts);
        self::assertSame('propertyShared.reference', $drifts[0]->propertyPath());
    }

    public function testAnEmptyTableYieldsNothing(): void
    {
        self::assertSame([], $this->scan(Scalar::class));
    }

    /**
     * @param class-string $class
     *
     * @return list<SharedDrift>
     */
    private function scan(string $class): array
    {
        $entityManager = $this->entityManager();
        $finder        = new LocaleVariantFinder($entityManager);
        $scanner       = new SharedDriftScanner($entityManager, $finder, new SharedValueSynchronizer($entityManager, $finder, $this->attributeHelper()), 'en_US');

        return iterator_to_array($scanner->scan($class), false);
    }

    /**
     * Persists, flushes, clears; returns the ids in argument order.
     *
     * @return list<int>
     */
    private function persistAll(object ...$entities): array
    {
        foreach ($entities as $entity) {
            $this->entityManager()->persist($entity);
        }

        $this->entityManager()->flush();

        $ids = [];
        foreach ($entities as $entity) {
            self::assertTrue(method_exists($entity, 'getId'));
            $id = $entity->getId();
            self::assertIsInt($id);
            $ids[] = $id;
        }

        $this->entityManager()->clear();

        return $ids;
    }
}
