<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine;

use PHPUnit\Framework\Attributes\CoversClass;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\SharedValueSynchronizer;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\EmbeddedSharedTranslatable;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\Translatable;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\InheritedIdEntity;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti\StiBook;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti\StiToy;
use Tmi\TranslationBundle\Fixtures\Entity\ReadonlyShared\ReadonlyShared;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\SharedDate\SharedDate;
use Tmi\TranslationBundle\Fixtures\Entity\SharedEnum\SharedEnum;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\NonTranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyUnidirectionalParent;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOneUnidirectional;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalParent;
use Tmi\TranslationBundle\Fixtures\Enum\Priority;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * The one discovery + copy of #[SharedAmongstTranslations] values, with the
 * EDITED row as source. Discovery is asserted class by class against the
 * fixtures that declare every form the bundle honours; the copy is asserted on
 * the SIBLING (reloaded where a row matters), never only on the source.
 */
#[CoversClass(SharedValueSynchronizer::class)]
final class SharedValueSynchronizerTest extends IntegrationTestCase
{
    // ------------------------------------------------------------------
    // Discovery
    // ------------------------------------------------------------------

    public function testDiscoversAMappedColumnAndSkipsSystemAndUnsharedColumns(): void
    {
        self::assertSame(['shared'], $this->paths(Scalar::class));
    }

    public function testDiscoversASubclassOnlyColumnFromTheConcreteClassAndNothingForItsSibling(): void
    {
        self::assertSame(['isbn'], $this->paths(StiBook::class));
        self::assertSame([], $this->paths(StiToy::class));
    }

    public function testDiscoversAPrivateColumnInheritedFromAMappedSuperclass(): void
    {
        self::assertSame(['sharedCode'], $this->paths(InheritedIdEntity::class));
    }

    /**
     * The attribute on the entity property shares the WHOLE embeddable: one
     * entry, whose change set appears in the UnitOfWork as one key per mapped
     * inner column.
     */
    public function testAWholeSharedEmbeddableIsOneEntryWithOneChangeSetPathPerColumn(): void
    {
        $entries = $this->synchronizer()->sharedProperties(Translatable::class);

        self::assertCount(1, $entries);
        self::assertSame('sharedAddress', $entries[0]['path']);
        self::assertNull($entries[0]['owner']);
        self::assertFalse($entries[0]['association']);
        self::assertEqualsCanonicalizing(
            ['sharedAddress.street', 'sharedAddress.postalCode', 'sharedAddress.city', 'sharedAddress.country'],
            $entries[0]['changeSetPaths'],
        );
    }

    /**
     * Sharing declared on the embeddable class (every inner property except an
     * #[EmptyOnTranslate] override) and on a single inner property.
     */
    public function testClassLevelAndInnerEmbeddableSharingAreOneEntryPerInnerProperty(): void
    {
        $entries = $this->synchronizer()->sharedProperties(EmbeddedSharedTranslatable::class);

        self::assertSame(['classShared.sharedByDefault', 'propertyShared.reference'], array_column($entries, 'path'));

        foreach ($entries as $entry) {
            self::assertNotNull($entry['owner']);
            self::assertSame([$entry['path']], $entry['changeSetPaths']);
        }
    }

    public function testDiscoversASingleValuedAssociationToANonTranslatableTargetButNotToATranslatableOne(): void
    {
        $entries = $this->synchronizer()->sharedProperties(TranslatableManyToOneUnidirectional::class);

        self::assertSame(['sharedToNonTranslatable'], array_column($entries, 'path'));
        self::assertTrue($entries[0]['association']);
    }

    public function testNeverDiscoversASharedCollection(): void
    {
        self::assertNotContains('sharedChildren', $this->paths(TranslatableOneToManyBidirectionalParent::class));
        self::assertNotContains('sharedChildren', $this->paths(TranslatableManyToManyUnidirectionalParent::class));
    }

    public function testResolvesAProxyClassToItsEntityClass(): void
    {
        $entity = new Scalar()->setLocale('en_US')->setTitle('EN');
        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();
        $id = $entity->getId();
        self::assertNotNull($id);
        $this->entityManager()->clear();

        $reference = $this->entityManager()->getReference(Scalar::class, $id);
        self::assertNotNull($reference);

        self::assertSame(['shared'], $this->paths($reference::class));
    }

    // ------------------------------------------------------------------
    // syncFrom(): the edited row is the source
    // ------------------------------------------------------------------

    public function testSyncFromUsesTheEditedRowAsSourceWhateverItsLocale(): void
    {
        $tuuid    = Tuuid::generate();
        $variants = $this->seedScalars($tuuid, ['en_US' => 'old', 'de_DE' => 'old', 'it_IT' => 'old']);

        $variants['de_DE']->setShared('from de_DE');

        $changed = $this->synchronizer()->syncFrom($variants['de_DE']);

        self::assertEqualsCanonicalizing(['en_US', 'it_IT'], array_map(static fn (TranslatableInterface $v): string|null => $v->getLocale(), $changed));

        $this->entityManager()->flush();

        foreach ($this->reloadScalars($tuuid) as $locale => $row) {
            self::assertSame('from de_DE', $row->getShared(), sprintf('%s must carry the edited value.', $locale));
        }
    }

    public function testSyncFromReturnsOnlyTheSiblingsThatActuallyChanged(): void
    {
        $tuuid    = Tuuid::generate();
        $variants = $this->seedScalars($tuuid, ['en_US' => 'same', 'de_DE' => 'same', 'it_IT' => 'stale']);

        $changed = $this->synchronizer()->syncFrom($variants['en_US']);

        self::assertCount(1, $changed);
        self::assertSame('it_IT', $changed[0]->getLocale());
        self::assertSame('same', $variants['it_IT']->getShared());
    }

    public function testALoneVariantHasNoSiblings(): void
    {
        $variants = $this->seedScalars(Tuuid::generate(), ['en_US' => 'alone']);

        self::assertSame([], $this->synchronizer()->syncFrom($variants['en_US']));
        self::assertSame([], $this->synchronizer()->siblingsOf($variants['en_US']));
    }

    public function testOnlyPropertiesRestrictsThePropagationToTheNamedPaths(): void
    {
        $tuuid = Tuuid::generate();

        $en = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $en->getClassShared()->setSharedByDefault('A');
        $en->getPropertyShared()->setReference('R1');

        $de = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $de->getClassShared()->setSharedByDefault('stale');
        $de->getPropertyShared()->setReference('stale');

        $this->persistAll($en, $de);

        $changed = $this->synchronizer()->syncFrom($en, ['propertyShared.reference']);

        self::assertCount(1, $changed);
        self::assertSame('R1', $de->getPropertyShared()->getReference());
        self::assertSame('stale', $de->getClassShared()->getSharedByDefault(), 'A path outside $onlyProperties must not be touched.');
    }

    public function testOnlyPropertiesSelectsAWholeEmbeddableByAnyOfItsColumns(): void
    {
        $tuuid = Tuuid::generate();

        $en = new Translatable()->setTuuid($tuuid)->setLocale('en_US');
        $en->getSharedAddress()?->setStreet('Via Roma 1')->setCity('Palermo');

        $de = new Translatable()->setTuuid($tuuid)->setLocale('de_DE');
        $de->getSharedAddress()?->setStreet('stale')->setCity('stale');

        $this->persistAll($en, $de);

        // The change-set key of one column selects the whole embeddable entry.
        $this->synchronizer()->syncFrom($en, ['sharedAddress.city']);

        $deAddress = $de->getSharedAddress();
        self::assertNotNull($deAddress);
        self::assertSame('Via Roma 1', $deAddress->getStreet());
        self::assertSame('Palermo', $deAddress->getCity());
        self::assertNotSame($en->getSharedAddress(), $deAddress, 'The embeddable is cloned, never the same instance.');
    }

    // ------------------------------------------------------------------
    // Value handling
    // ------------------------------------------------------------------

    public function testAValueObjectIsClonedOntoTheSibling(): void
    {
        $tuuid = Tuuid::generate();
        $date  = new \DateTimeImmutable('2026-09-05 10:00:00');

        $en = new SharedDate()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN')->setPublishedAt($date);
        $de = new SharedDate()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setPublishedAt(new \DateTimeImmutable('2020-01-01'));

        $this->persistAll($en, $de);

        $this->synchronizer()->syncFrom($en);

        self::assertEquals($date, $de->getPublishedAt());
        self::assertNotSame($en->getPublishedAt(), $de->getPublishedAt(), 'Two rows must never share one mutable value-object instance.');
    }

    /**
     * `clone` on an enum throws an Error -- a copy that cloned every object
     * value would fail here.
     */
    public function testAnEnumIsAssignedNeverCloned(): void
    {
        $tuuid = Tuuid::generate();

        $en = new SharedEnum()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN')->setPriority(Priority::High);
        $de = new SharedEnum()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setPriority(Priority::Low);

        $this->persistAll($en, $de);

        $this->synchronizer()->syncFrom($en);

        self::assertSame(Priority::High, $de->getPriority());
    }

    public function testAnAssociationToANonTranslatableTargetIsSharedAsTheIdenticalInstance(): void
    {
        $tuuid = Tuuid::generate();
        $child = new NonTranslatableManyToOneBidirectionalChild();

        $en = new TranslatableManyToOneUnidirectional()->setTuuid($tuuid)->setLocale('en_US')->setSharedToNonTranslatable($child);
        $de = new TranslatableManyToOneUnidirectional()->setTuuid($tuuid)->setLocale('de_DE');

        $this->persistAll($en, $de);

        $changed = $this->synchronizer()->syncFrom($en);

        self::assertCount(1, $changed);
        self::assertSame($child, $de->getSharedToNonTranslatable());

        $this->entityManager()->flush();
        $deId = $de->getId();
        self::assertNotNull($deId);
        $this->entityManager()->clear();

        $reloaded = $this->withoutFilter(fn (): TranslatableManyToOneUnidirectional|null => $this->entityManager()->find(TranslatableManyToOneUnidirectional::class, $deId));
        self::assertNotNull($reloaded);
        self::assertSame($child->getId(), $reloaded->getSharedToNonTranslatable()?->getId());
    }

    public function testCompareReportsWithoutWriting(): void
    {
        $tuuid = Tuuid::generate();

        $en = new ReadonlyShared('SKU-EN')->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN')->setNote('canonical');
        $de = new ReadonlyShared('SKU-DE')->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setNote('stale');

        $this->persistAll($en, $de);

        $report = $this->synchronizer()->compare($en, $de);

        self::assertSame(['note'], $report->changed());
        self::assertSame(['sku'], $report->readonlyDrift());
        self::assertTrue($report->hasChanges());
        self::assertSame('stale', $de->getNote(), 'compare() must not write.');
    }

    public function testSyncWritesTheWritableDriftAndReportsTheReadonlyOne(): void
    {
        $tuuid = Tuuid::generate();

        $en = new ReadonlyShared('SKU-EN')->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN')->setNote('canonical');
        $de = new ReadonlyShared('SKU-DE')->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setNote('stale');

        $this->persistAll($en, $de);

        $report = $this->synchronizer()->sync($en, $de);

        self::assertSame(['note'], $report->changed());
        self::assertSame(['sku'], $report->readonlyDrift());
        self::assertSame('canonical', $de->getNote());
        self::assertSame('SKU-DE', $de->getSku(), 'A readonly property is reported, never written.');
    }

    public function testAnUninitializedSourcePropertyIsSkipped(): void
    {
        $source = new \ReflectionClass(Translatable::class)->newInstanceWithoutConstructor();

        $sibling = new Translatable();
        $sibling->getSharedAddress()?->setStreet('keep');

        $report = $this->synchronizer()->sync($source, $sibling);

        self::assertFalse($report->hasChanges());
        self::assertSame('keep', $sibling->getSharedAddress()?->getStreet());
    }

    public function testAnUninitializedSiblingPropertyReadsAsNullAndReceivesTheValue(): void
    {
        $source = new Translatable();
        $source->getSharedAddress()?->setStreet('Via Roma 1');

        $sibling = new \ReflectionClass(Translatable::class)->newInstanceWithoutConstructor();

        $report = $this->synchronizer()->sync($source, $sibling);

        self::assertSame(['sharedAddress'], $report->changed());
        self::assertSame('Via Roma 1', $sibling->getSharedAddress()?->getStreet());
    }

    public function testAnUninitializedEmbeddableOwnerOnEitherSideIsSkipped(): void
    {
        $bare = new \ReflectionClass(EmbeddedSharedTranslatable::class)->newInstanceWithoutConstructor();

        $full = new EmbeddedSharedTranslatable();
        $full->getPropertyShared()->setReference('R1');

        self::assertFalse($this->synchronizer()->sync($bare, $full)->hasChanges());
        self::assertFalse($this->synchronizer()->sync($full, $bare)->hasChanges());
        self::assertSame('R1', $full->getPropertyShared()->getReference());
    }

    // ------------------------------------------------------------------
    // valuesEqual()
    // ------------------------------------------------------------------

    public function testValuesEqualComparesDistinctDateTimeImmutableInstancesByValue(): void
    {
        $a = new \DateTimeImmutable('2024-01-01 12:00:00', new \DateTimeZone('UTC'));
        $b = new \DateTimeImmutable('2024-01-01 13:00:00', new \DateTimeZone('Europe/Berlin'));

        self::assertNotSame($a, $b);
        self::assertTrue(SharedValueSynchronizer::valuesEqual($a, $b));
    }

    public function testValuesEqualReturnsFalseForDifferentDateTimeImmutableTimestamps(): void
    {
        $a = new \DateTimeImmutable('2024-01-01 12:00:00', new \DateTimeZone('UTC'));
        $b = new \DateTimeImmutable('2024-01-01 12:00:01', new \DateTimeZone('UTC'));

        self::assertFalse(SharedValueSynchronizer::valuesEqual($a, $b));
    }

    public function testValuesEqualFallsThroughToSerializeWhenOnlyOneSideIsDateTime(): void
    {
        $a = new \DateTimeImmutable('2024-01-01 12:00:00', new \DateTimeZone('UTC'));
        $b = new \stdClass();

        self::assertFalse(SharedValueSynchronizer::valuesEqual($a, $b));
    }

    public function testValuesEqualIsStrictForScalarsAndIdentityForEnums(): void
    {
        self::assertTrue(SharedValueSynchronizer::valuesEqual(1, 1));
        self::assertFalse(SharedValueSynchronizer::valuesEqual(1, '1'));
        self::assertTrue(SharedValueSynchronizer::valuesEqual(Priority::High, Priority::High));
        self::assertFalse(SharedValueSynchronizer::valuesEqual(Priority::High, Priority::Low));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function synchronizer(): SharedValueSynchronizer
    {
        $synchronizer = self::getContainer()->get('test.shared_value_synchronizer');
        self::assertInstanceOf(SharedValueSynchronizer::class, $synchronizer);

        return $synchronizer;
    }

    /**
     * @param class-string $class
     *
     * @return list<string>
     */
    private function paths(string $class): array
    {
        return array_column($this->synchronizer()->sharedProperties($class), 'path');
    }

    private function persistAll(object ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->entityManager()->persist($entity);
        }

        $this->entityManager()->flush();
    }

    /**
     * Persists one Scalar per locale, then reloads them MANAGED from a cleared
     * EntityManager -- the state an application holds a just-edited row in.
     *
     * @param array<string, string> $sharedByLocale locale => shared value
     *
     * @return array<string, Scalar> locale => managed row
     */
    private function seedScalars(Tuuid $tuuid, array $sharedByLocale): array
    {
        foreach ($sharedByLocale as $locale => $shared) {
            $this->entityManager()->persist(
                new Scalar()->setTuuid($tuuid)->setLocale($locale)->setTitle('Title '.$locale)->setShared($shared),
            );
        }

        $this->entityManager()->flush();

        return $this->reloadScalars($tuuid);
    }

    /**
     * @return array<string, Scalar> locale => row, from a cleared EntityManager
     */
    private function reloadScalars(Tuuid $tuuid): array
    {
        $this->entityManager()->clear();

        $rows = [];

        foreach (new LocaleVariantFinder($this->entityManager())->findAllLocaleVariants(Scalar::class, $tuuid) as $locale => $row) {
            self::assertInstanceOf(Scalar::class, $row);
            $rows[$locale] = $row;
        }

        return $rows;
    }

    /**
     * @template T
     *
     * @param callable(): T $query
     *
     * @return T
     */
    private function withoutFilter(callable $query): mixed
    {
        return new LocaleVariantFinder($this->entityManager())->withoutLocaleFilter($query);
    }
}
