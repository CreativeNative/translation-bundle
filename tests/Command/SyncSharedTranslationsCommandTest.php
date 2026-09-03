<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tmi\TranslationBundle\Command\SyncSharedTranslationsCommand;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\TranslatableEntityLocator;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\EmbeddedSharedTranslatable;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\InheritedIdEntity;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\PrivateIdSuperclass;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti\StiBook;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti\StiToy;
use Tmi\TranslationBundle\Fixtures\Entity\ReadonlyShared\ReadonlyShared;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\SharedDate\SharedDate;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\Utils\AttributeHelper;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class SyncSharedTranslationsCommandTest extends IntegrationTestCase
{
    public function testPropagatesSharedValueToSiblings(): void
    {
        $deId = $this->seedPair('English shared', 'Stale german shared');

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 translation(s) updated', $tester->getDisplay());
        self::assertSame('English shared', $this->reloadShared($deId));
    }

    public function testDryRunDoesNotWrite(): void
    {
        $deId = $this->seedPair('English shared', 'Stale german shared');

        $tester = $this->run_(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('would be updated', $tester->getDisplay());
        self::assertSame('Stale german shared', $this->reloadShared($deId));
    }

    public function testCheckExitsNonZeroOnDriftAndDoesNotWrite(): void
    {
        $deId = $this->seedPair('English shared', 'Stale german shared');

        $tester = $this->run_(['--check' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('differ from their source', $tester->getDisplay());
        self::assertSame('Stale german shared', $this->reloadShared($deId));
    }

    public function testCheckPassesAfterSyncRepairsDrift(): void
    {
        $deId = $this->seedPair('English shared', 'Stale german shared');

        self::assertSame(Command::FAILURE, $this->run_(['--check' => true])->getStatusCode());

        self::assertSame(Command::SUCCESS, $this->run_()->getStatusCode());
        self::assertSame('English shared', $this->reloadShared($deId));

        $tester = $this->run_(['--check' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('already in sync', $tester->getDisplay());
    }

    public function testCheckExitsNonZeroOnReadonlyDrift(): void
    {
        $tuuid = Tuuid::generate();

        $this->persistPair(
            new ReadonlyShared('SKU-EN')->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN')->setNote('same'),
            new ReadonlyShared('SKU-DE')->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setNote('same'),
        );

        $tester = $this->run_(['--check' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('readonly shared value(s)', $tester->getDisplay());
    }

    public function testReportsWhenAlreadyInSync(): void
    {
        $this->seedPair('Same shared', 'Same shared');

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('already in sync', $tester->getDisplay());
    }

    public function testEntityOptionRestrictsToOneClass(): void
    {
        $deId = $this->seedPair('English shared', 'Stale german shared');

        $tester = $this->run_(['--entity' => Scalar::class]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('English shared', $this->reloadShared($deId));
    }

    public function testEntityOptionRejectsUnknownClass(): void
    {
        $tester = $this->run_(['--entity' => 'App\\Entity\\DoesNotExist']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('not a known translatable entity', $tester->getDisplay());
    }

    /**
     * A real, loadable class that Doctrine has no mapping for at all --
     * distinct from a nonexistent class name (testEntityOptionRejectsUnknownClass)
     * and from a mapped superclass (testEntityOptionRejectsAMappedSuperclass):
     * isTranslatableEntity() must reject it at the isTransient() check.
     */
    public function testEntityOptionRejectsARealClassThatIsNotDoctrineMapped(): void
    {
        $tester = $this->run_(['--entity' => \stdClass::class]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('not a known translatable entity', $tester->getDisplay());
    }

    /**
     * A mapped superclass is not transient and passes isTransient() as
     * "mapped", but it has no table of its own to sync -- isTranslatableEntity()
     * must still reject it.
     */
    public function testEntityOptionRejectsAMappedSuperclass(): void
    {
        $tester = $this->run_(['--entity' => PrivateIdSuperclass::class]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('not a known translatable entity', $tester->getDisplay());
    }

    /**
     * StiBook::$isbn is #[SharedAmongstTranslations] but declared only on the
     * concrete subclass, not on the queried root -- StiRoot's own reflection
     * walk never sees it. The command must still resolve it from each tuuid
     * group's own hydrated (StiBook) instance.
     */
    public function testSyncsASharedFieldDeclaredOnlyOnAConcreteSubclass(): void
    {
        $tuuid = Tuuid::generate();

        $deId = $this->persistPair(
            new StiBook()->setIsbn('978-EN')->setTuuid($tuuid)->setLocale('en_US'),
            new StiBook()->setIsbn('978-STALE')->setTuuid($tuuid)->setLocale('de_DE'),
        );

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 translation(s) updated', $tester->getDisplay());

        $this->entityManager()->clear();
        $this->entityManager()->getFilters()->disable('tmi_translation_locale_filter');
        $reloaded = $this->entityManager()->find(StiBook::class, $deId);
        self::assertInstanceOf(StiBook::class, $reloaded);
        self::assertSame('978-EN', $reloaded->getIsbn());
    }

    /**
     * --entity must accept a concrete subclass even though
     * TranslatableEntityLocator::locate() now names only the hierarchy root.
     */
    public function testEntityOptionAcceptsAConcreteSubclassNotNamedByTheLocator(): void
    {
        $tuuid = Tuuid::generate();

        $deId = $this->persistPair(
            new StiBook()->setIsbn('978-EN')->setTuuid($tuuid)->setLocale('en_US'),
            new StiBook()->setIsbn('978-STALE')->setTuuid($tuuid)->setLocale('de_DE'),
        );

        $tester = $this->run_(['--entity' => StiBook::class]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $this->entityManager()->clear();
        $this->entityManager()->getFilters()->disable('tmi_translation_locale_filter');
        $reloaded = $this->entityManager()->find(StiBook::class, $deId);
        self::assertInstanceOf(StiBook::class, $reloaded);
        self::assertSame('978-EN', $reloaded->getIsbn());
    }

    /**
     * StiToy declares no #[SharedAmongstTranslations] property at all. Mixed
     * into the same hierarchy as StiBook (which does), its tuuid group must
     * be left alone -- not crash, not falsely report drift -- while the
     * book's isbn still gets synced.
     */
    public function testSkipsAConcreteSubclassWithNoSharedPropertyInAMixedHierarchy(): void
    {
        $bookTuuid = Tuuid::generate();
        $toyTuuid  = Tuuid::generate();

        $this->persistPair(
            new StiBook()->setIsbn('978-EN')->setTuuid($bookTuuid)->setLocale('en_US'),
            new StiBook()->setIsbn('978-STALE')->setTuuid($bookTuuid)->setLocale('de_DE'),
        );

        $toyDeId = $this->persistPair(
            new StiToy()->setMaterial('Wood')->setTuuid($toyTuuid)->setLocale('en_US'),
            new StiToy()->setMaterial('Plastic')->setTuuid($toyTuuid)->setLocale('de_DE'),
        );

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 translation(s) updated', $tester->getDisplay());

        $this->entityManager()->clear();
        $this->entityManager()->getFilters()->disable('tmi_translation_locale_filter');
        $toy = $this->entityManager()->find(StiToy::class, $toyDeId);
        self::assertInstanceOf(StiToy::class, $toy);
        self::assertSame('Plastic', $toy->getMaterial());
    }

    public function testPropagatesObjectValuedSharedField(): void
    {
        $tuuid = Tuuid::generate();

        $source = new SharedDate()
            ->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN')
            ->setPublishedAt(new \DateTimeImmutable('2020-01-01 00:00:00'));
        $sibling = new SharedDate()
            ->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')
            ->setPublishedAt(new \DateTimeImmutable('2021-06-15 00:00:00'));

        $this->entityManager()->persist($source);
        $this->entityManager()->persist($sibling);
        $this->entityManager()->flush();
        $siblingId = $sibling->getId();
        self::assertNotNull($siblingId);
        $this->entityManager()->clear();

        $tester = $this->run_();
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $this->entityManager()->clear();
        $this->entityManager()->getFilters()->disable('tmi_translation_locale_filter');
        $reloaded = $this->entityManager()->find(SharedDate::class, $siblingId);
        self::assertInstanceOf(SharedDate::class, $reloaded);
        self::assertEquals(new \DateTimeImmutable('2020-01-01 00:00:00'), $reloaded->getPublishedAt());
    }

    /**
     * The command orders its streaming query by tuuid, so sibling locale variants of
     * the same tuuid land next to each other in the result set regardless of physical
     * insertion/PK order. Persisting two tuuid groups with interleaved rows -- B, A, B,
     * A instead of A, A, B, B -- proves the grouping comes from the ORDER BY clause and
     * not from an assumption that rows already arrive pre-grouped.
     */
    public function testGroupsByTuuidRegardlessOfInsertionOrder(): void
    {
        $tuuidA = Tuuid::generate();
        $tuuidB = Tuuid::generate();

        $bEn = new Scalar()->setTuuid($tuuidB)->setLocale('en_US')->setTitle('B EN')->setShared('B shared');
        $aEn = new Scalar()->setTuuid($tuuidA)->setLocale('en_US')->setTitle('A EN')->setShared('A shared');
        $bDe = new Scalar()->setTuuid($tuuidB)->setLocale('de_DE')->setTitle('B DE')->setShared('B stale');
        $aDe = new Scalar()->setTuuid($tuuidA)->setLocale('de_DE')->setTitle('A DE')->setShared('A stale');

        $this->entityManager()->persist($bEn);
        $this->entityManager()->persist($aEn);
        $this->entityManager()->persist($bDe);
        $this->entityManager()->persist($aDe);
        $this->entityManager()->flush();

        $aDeId = $aDe->getId();
        $bDeId = $bDe->getId();
        self::assertNotNull($aDeId);
        self::assertNotNull($bDeId);

        $this->entityManager()->clear();

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('2 translation(s) updated', $tester->getDisplay());
        self::assertSame('A shared', $this->reloadShared($aDeId));
        self::assertSame('B shared', $this->reloadShared($bDeId));
    }

    /**
     * SyncSharedTranslationsCommand flushes and detaches every 10 completed tuuid
     * groups while streaming, instead of holding the whole table in memory. Seeding 11
     * groups forces one such mid-stream batch boundary (after the 10th group).
     *
     * The 11th group's sibling (de_DE) is persisted *before* its source (en_US), which
     * on SQLite is enough to make it the first row ORDER BY tuuid returns for that
     * group -- i.e. the "lookahead" row the streaming loop reads to discover the batch
     * is complete, before that row's own group has been synced. A batching
     * implementation that detaches via a blanket EntityManager::clear() at the
     * boundary would detach this lookahead entity too, and its later mutation would
     * never reach the database -- so this also guards the flush/detach split, not just
     * the batch-boundary branch.
     */
    public function testFlushesAndDetachesInBatchesDuringApply(): void
    {
        $groupCount = 11;
        $siblingIds = [];

        for ($i = 0; $i < $groupCount - 1; ++$i) {
            $tuuid = Tuuid::generate();

            $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN'.$i)->setShared('canonical'.$i);
            $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE'.$i)->setShared('stale'.$i);

            $this->entityManager()->persist($en);
            $this->entityManager()->persist($de);
            $this->entityManager()->flush();

            $id = $de->getId();
            self::assertNotNull($id);
            $siblingIds[$i] = $id;
        }

        $lastTuuid = Tuuid::generate();
        $lastIndex = $groupCount - 1;
        $lastDe    = new Scalar()->setTuuid($lastTuuid)->setLocale('de_DE')->setTitle('DE'.$lastIndex)->setShared('stale'.$lastIndex);
        $lastEn    = new Scalar()->setTuuid($lastTuuid)->setLocale('en_US')->setTitle('EN'.$lastIndex)->setShared('canonical'.$lastIndex);

        $this->entityManager()->persist($lastDe);
        $this->entityManager()->persist($lastEn);
        $this->entityManager()->flush();

        $lastId = $lastDe->getId();
        self::assertNotNull($lastId);
        $siblingIds[$lastIndex] = $lastId;

        $this->entityManager()->clear();

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(sprintf('%d translation(s) updated', $groupCount), $tester->getDisplay());

        for ($i = 0; $i < $groupCount; ++$i) {
            self::assertSame('canonical'.$i, $this->reloadShared($siblingIds[$i]), sprintf('group %d did not sync', $i));
        }
    }

    /**
     * A #[SharedAmongstTranslations] column declared PRIVATE on a mapped
     * superclass must be seen by the back-fill — a child-class-only property
     * walk reports "already in sync" while the rows stay divergent.
     */
    public function testPropagatesInheritedPrivateSharedProperty(): void
    {
        $tuuid = Tuuid::generate();

        $en = new InheritedIdEntity()->setTuuid($tuuid)->setLocale('en_US');
        $en->setTitle('EN');
        $en->setSharedCode('canonical');

        $de = new InheritedIdEntity()->setTuuid($tuuid)->setLocale('de_DE');
        $de->setTitle('DE');
        $de->setSharedCode('stale');

        $deId = $this->persistPair($en, $de);

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 translation(s) updated', $tester->getDisplay());

        $this->entityManager()->clear();
        $this->entityManager()->getFilters()->disable('tmi_translation_locale_filter');
        $reloaded = $this->entityManager()->find(InheritedIdEntity::class, $deId);
        self::assertInstanceOf(InheritedIdEntity::class, $reloaded);
        self::assertSame('canonical', $reloaded->getSharedCode());
    }

    public function testPickSourceFallsBackWhenNoDefaultLocaleVariant(): void
    {
        $tuuid = Tuuid::generate();

        // No en_US variant — pickSource must fall back to the first variant.
        $this->entityManager()->persist(
            new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setShared('German shared'),
        );
        $this->entityManager()->persist(
            new Scalar()->setTuuid($tuuid)->setLocale('it_IT')->setTitle('IT')->setShared('Italian shared'),
        );
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 translation(s) updated', $tester->getDisplay());
    }

    /**
     * Form 2: #[SharedAmongstTranslations] on the embeddable CLASS. The entity property
     * carries no attribute at all, so the old field-only scan reported "already in sync"
     * while rows stayed divergent.
     */
    public function testPropagatesSharingDeclaredOnTheEmbeddableClass(): void
    {
        $tuuid = Tuuid::generate();

        $en = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $en->getClassShared()->setSharedByDefault('canonical')->setOverriddenToEmpty('EN note');

        $de = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $de->getClassShared()->setSharedByDefault('stale')->setOverriddenToEmpty('DE note');

        $deId = $this->persistPair($en, $de);

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $reloaded = $this->reloadEmbeddedShared($deId);
        self::assertSame('canonical', $reloaded->getClassShared()->getSharedByDefault());

        // A property-level #[EmptyOnTranslate] overrides the class-level sharing, exactly as
        // EmbeddedHandler resolves it at translate time.
        self::assertSame('DE note', $reloaded->getClassShared()->getOverriddenToEmpty());
    }

    /**
     * Form 3: #[SharedAmongstTranslations] on an inner property of the embeddable only.
     */
    public function testPropagatesSharingDeclaredOnAnInnerEmbeddableProperty(): void
    {
        $tuuid = Tuuid::generate();

        $en = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $en->getPropertyShared()->setReference('REF-1')->setLabel('English label');

        $de = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $de->getPropertyShared()->setReference('REF-STALE')->setLabel('Deutsches Label');

        $deId = $this->persistPair($en, $de);

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $reloaded = $this->reloadEmbeddedShared($deId);
        self::assertSame('REF-1', $reloaded->getPropertyShared()->getReference());

        // Not marked shared -- must keep its own translated value
        self::assertSame('Deutsches Label', $reloaded->getPropertyShared()->getLabel());
    }

    public function testEmbeddableWithoutSharingIsLeftAlone(): void
    {
        $tuuid = Tuuid::generate();

        $en = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $en->getPropertyShared()->setReference('REF-1')->setLabel('English label');

        $de = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $de->getPropertyShared()->setReference('REF-1')->setLabel('Deutsches Label');

        $deId = $this->persistPair($en, $de);

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('already in sync', $tester->getDisplay());
        self::assertSame('Deutsches Label', $this->reloadEmbeddedShared($deId)->getPropertyShared()->getLabel());
    }

    public function testReadonlySharedDriftIsReportedInsteadOfCrashing(): void
    {
        $tuuid = Tuuid::generate();

        $deId = $this->persistPair(
            new ReadonlyShared('SKU-EN')->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN')->setNote('canonical'),
            new ReadonlyShared('SKU-DE')->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setNote('stale'),
        );

        $tester = $this->run_();

        // Diagnostics-style exit code: the run completed but drift remains
        self::assertSame(Command::FAILURE, $tester->getStatusCode());

        $display = $tester->getDisplay();
        self::assertStringContainsString('readonly shared value(s)', $display);
        self::assertStringContainsString('ReadonlyShared::$sku', $display);
        self::assertStringContainsString('de_DE', $display);

        $this->entityManager()->clear();
        $this->entityManager()->getFilters()->disable('tmi_translation_locale_filter');
        $reloaded = $this->entityManager()->find(ReadonlyShared::class, $deId);
        self::assertInstanceOf(ReadonlyShared::class, $reloaded);

        // The readonly value is untouched, but the writable shared property still synced
        self::assertSame('SKU-DE', $reloaded->getSku());
        self::assertSame('canonical', $reloaded->getNote());
    }

    public function testReadonlySharedDriftIsReportedInDryRunToo(): void
    {
        $tuuid = Tuuid::generate();

        $this->persistPair(
            new ReadonlyShared('SKU-EN')->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN')->setNote('same'),
            new ReadonlyShared('SKU-DE')->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setNote('same'),
        );

        $tester = $this->run_(['--dry-run' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('readonly shared value(s)', $tester->getDisplay());
    }

    /**
     * Only the writable $note has drifted (both instances share the same sku) --
     * the drift table names it, with no row at all for the untouched $sku.
     */
    public function testDriftTableNamesAWritablePropertyThatDrifted(): void
    {
        $tuuid = Tuuid::generate();

        $this->persistPair(
            new ReadonlyShared('SKU-SAME')->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN')->setNote('canonical'),
            new ReadonlyShared('SKU-SAME')->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setNote('stale'),
        );

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $display = self::normalizeTable($tester->getDisplay());
        self::assertMatchesRegularExpression('/note\s+1\s+1\s+yes/', $display);
        self::assertDoesNotMatchRegularExpression('/\bsku\b/', $display);
    }

    /**
     * Only the readonly $sku has drifted (both instances share the same note) --
     * the drift table marks it not writable, alongside the existing readonly listing.
     */
    public function testDriftTableMarksAReadonlyPropertyAsNotWritable(): void
    {
        $tuuid = Tuuid::generate();

        $this->persistPair(
            new ReadonlyShared('SKU-EN')->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN')->setNote('same'),
            new ReadonlyShared('SKU-DE')->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setNote('same'),
        );

        $tester = $this->run_();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());

        $display = self::normalizeTable($tester->getDisplay());
        self::assertMatchesRegularExpression('/sku\s+1\s+1\s+no/', $display);
        self::assertStringContainsString('readonly shared value(s)', $tester->getDisplay());
        self::assertStringContainsString('ReadonlyShared::$sku', $tester->getDisplay());
    }

    /**
     * Two independent tuuid groups both drift on $shared: the table counts two
     * distinct tuuids and two rows.
     */
    public function testDriftTableCountsDistinctTuuidsAcrossGroups(): void
    {
        $tuuidA = Tuuid::generate();
        $tuuidB = Tuuid::generate();

        $aEn = new Scalar()->setTuuid($tuuidA)->setLocale('en_US')->setTitle('A EN')->setShared('A shared');
        $aDe = new Scalar()->setTuuid($tuuidA)->setLocale('de_DE')->setTitle('A DE')->setShared('A stale');
        $bEn = new Scalar()->setTuuid($tuuidB)->setLocale('en_US')->setTitle('B EN')->setShared('B shared');
        $bDe = new Scalar()->setTuuid($tuuidB)->setLocale('de_DE')->setTitle('B DE')->setShared('B stale');

        $this->entityManager()->persist($aEn);
        $this->entityManager()->persist($aDe);
        $this->entityManager()->persist($bEn);
        $this->entityManager()->persist($bDe);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $tester = $this->run_(['--check' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());

        $display = self::normalizeTable($tester->getDisplay());
        self::assertMatchesRegularExpression('/shared\s+2\s+2\s+yes/', $display);
    }

    /**
     * One tuuid group with three siblings, all diverging from the source: the
     * table counts one distinct tuuid but three drifted rows -- tuuids and rows
     * are not the same count.
     */
    public function testDriftTableCountsEachSiblingRowSeparatelyWithinOneGroup(): void
    {
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN')->setShared('canonical');
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setShared('stale-de');
        $fr = new Scalar()->setTuuid($tuuid)->setLocale('fr_FR')->setTitle('FR')->setShared('stale-fr');
        $it = new Scalar()->setTuuid($tuuid)->setLocale('it_IT')->setTitle('IT')->setShared('stale-it');

        $this->entityManager()->persist($en);
        $this->entityManager()->persist($de);
        $this->entityManager()->persist($fr);
        $this->entityManager()->persist($it);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $tester = $this->run_(['--check' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());

        $display = self::normalizeTable($tester->getDisplay());
        self::assertMatchesRegularExpression('/shared\s+1\s+3\s+yes/', $display);
    }

    /**
     * --check and --dry-run write nothing but still render the drift table --
     * it is diagnostic output, not tied to --apply.
     */
    public function testDriftTableIsShownInCheckAndDryRunModes(): void
    {
        $deId = $this->seedPair('English shared', 'Stale german shared');

        $checkDisplay = self::normalizeTable($this->run_(['--check' => true])->getDisplay());
        self::assertMatchesRegularExpression('/shared\s+1\s+1\s+yes/', $checkDisplay);
        self::assertSame('Stale german shared', $this->reloadShared($deId));

        $dryRunDisplay = self::normalizeTable($this->run_(['--dry-run' => true])->getDisplay());
        self::assertMatchesRegularExpression('/shared\s+1\s+1\s+yes/', $dryRunDisplay);
        self::assertSame('Stale german shared', $this->reloadShared($deId));
    }

    /**
     * The drift table also resolves embedded property paths -- "propertyShared.reference" --
     * exactly as {@see SyncSharedTranslationsCommand::propertyPath()} names them elsewhere.
     */
    public function testDriftTableNamesAnEmbeddedPropertyPath(): void
    {
        $tuuid = Tuuid::generate();

        $en = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $en->getPropertyShared()->setReference('REF-1')->setLabel('English label');

        $de = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $de->getPropertyShared()->setReference('REF-STALE')->setLabel('Deutsches Label');

        $this->persistPair($en, $de);

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $display = self::normalizeTable($tester->getDisplay());
        self::assertMatchesRegularExpression('/propertyShared\.reference\s+1\s+1\s+yes/', $display);
    }

    /**
     * "already in sync" prints no drift table at all -- the table only appears
     * when something actually drifted.
     */
    public function testNoDriftTableWhenAlreadyInSync(): void
    {
        $this->seedPair('Same shared', 'Same shared');

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('already in sync', $tester->getDisplay());
        self::assertStringNotContainsString('Property', $tester->getDisplay());
        self::assertStringNotContainsString('Writable', $tester->getDisplay());
    }

    public function testValuesEqualComparesDistinctDateTimeImmutableInstancesByValue(): void
    {
        $a = new \DateTimeImmutable('2024-01-01 12:00:00', new \DateTimeZone('UTC'));
        $b = new \DateTimeImmutable('2024-01-01 13:00:00', new \DateTimeZone('Europe/Berlin'));

        self::assertNotSame($a, $b);
        self::assertTrue($this->valuesEqual($a, $b));
    }

    public function testValuesEqualReturnsFalseForDifferentDateTimeImmutableTimestamps(): void
    {
        $a = new \DateTimeImmutable('2024-01-01 12:00:00', new \DateTimeZone('UTC'));
        $b = new \DateTimeImmutable('2024-01-01 12:00:01', new \DateTimeZone('UTC'));

        self::assertFalse($this->valuesEqual($a, $b));
    }

    public function testValuesEqualFallsThroughToSerializeWhenOnlyOneSideIsDateTime(): void
    {
        $a = new \DateTimeImmutable('2024-01-01 12:00:00', new \DateTimeZone('UTC'));
        $b = new \stdClass();

        self::assertFalse($this->valuesEqual($a, $b));
    }

    public function testReportsWhenNoTranslatableEntitiesExist(): void
    {
        $factory = self::createStub(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willReturn([]);

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getMetadataFactory')->willReturn($factory);

        $command = new SyncSharedTranslationsCommand(
            $entityManager,
            new TranslatableEntityLocator($entityManager),
            new AttributeHelper(),
            'en_US',
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No translatable entities', $tester->getDisplay());
    }

    /**
     * SymfonyStyle::table() wraps long cells across output lines when the terminal
     * width is narrow (CommandTester defaults to a narrow width), and renders columns
     * with padding rather than a "|" separator -- collapsing whitespace/newlines keeps
     * the row assertions above readable and stable regardless of wrapping or padding.
     */
    private static function normalizeTable(string $display): string
    {
        return (string) preg_replace('/\s+/', ' ', $display);
    }

    /**
     * @return int The sibling entity id
     */
    private function persistPair(TranslatableInterface $source, TranslatableInterface $sibling): int
    {
        $this->entityManager()->persist($source);
        $this->entityManager()->persist($sibling);
        $this->entityManager()->flush();

        self::assertTrue(method_exists($sibling, 'getId'));
        $id = $sibling->getId();
        self::assertIsInt($id);

        $this->entityManager()->clear();

        return $id;
    }

    private function reloadEmbeddedShared(int $id): EmbeddedSharedTranslatable
    {
        $this->entityManager()->clear();
        $this->entityManager()->getFilters()->disable('tmi_translation_locale_filter');

        $entity = $this->entityManager()->find(EmbeddedSharedTranslatable::class, $id);
        self::assertInstanceOf(EmbeddedSharedTranslatable::class, $entity);

        return $entity;
    }

    /**
     * Persists an en_US source and a de_DE sibling sharing one Tuuid.
     *
     * @return int The de_DE entity id
     */
    private function seedPair(string $sourceShared, string $siblingShared): int
    {
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN')->setShared($sourceShared);
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setShared($siblingShared);

        $this->entityManager()->persist($en);
        $this->entityManager()->persist($de);
        $this->entityManager()->flush();

        $id = $de->getId();
        self::assertNotNull($id);

        $this->entityManager()->clear();

        return $id;
    }

    private function reloadShared(int $id): string|null
    {
        $this->entityManager()->clear();

        $filters = $this->entityManager()->getFilters();
        if ($filters->isEnabled('tmi_translation_locale_filter')) {
            $filters->disable('tmi_translation_locale_filter');
        }

        $entity = $this->entityManager()->find(Scalar::class, $id);
        self::assertInstanceOf(Scalar::class, $entity);

        return $entity->getShared();
    }

    /**
     * SyncSharedTranslationsCommand::valuesEqual() is private static -- reflection is the
     * narrowest way to unit-test its DateTimeInterface fast path directly, instead of only
     * exercising it indirectly through a full sync run.
     */
    private function valuesEqual(mixed $a, mixed $b): bool
    {
        $method = new \ReflectionMethod(SyncSharedTranslationsCommand::class, 'valuesEqual');

        $result = $method->invoke(null, $a, $b);
        self::assertIsBool($result);

        return $result;
    }

    /**
     * @param array<string, bool|string> $input
     */
    private function run_(array $input = []): CommandTester
    {
        $command = new SyncSharedTranslationsCommand(
            $this->entityManager(),
            new TranslatableEntityLocator($this->entityManager()),
            $this->attributeHelper(),
            'en_US',
        );

        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }
}
