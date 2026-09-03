<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tmi\TranslationBundle\Command\TranslationDoctorCommand;
use Tmi\TranslationBundle\Doctrine\TranslatableEntityLocator;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\PrivateIdSuperclass;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti\StiBook;
use Tmi\TranslationBundle\Fixtures\Entity\Legacy\NullableTuuidEntity;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class TranslationDoctorCommandTest extends IntegrationTestCase
{
    /** @var list<string> */
    private const array LOCALES = ['en_US', 'de_DE', 'it_IT'];

    public function testReportsHealthyDataset(): void
    {
        $tuuid = Tuuid::generate();

        foreach (self::LOCALES as $locale) {
            $entity = new Scalar()->setTuuid($tuuid)->setLocale($locale)->setTitle($locale);
            $this->entityManager()->persist($entity);
        }
        $this->entityManager()->flush();

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('correctly linked', $tester->getDisplay());
    }

    public function testDetectsStandaloneTranslation(): void
    {
        $entity = new Scalar()->setLocale('en_US')->setTitle('Lonely');
        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();

        $tester = $this->run_();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Standalone', $tester->getDisplay());
    }

    public function testDetectsIncompleteTranslation(): void
    {
        $tuuid = Tuuid::generate();

        $this->entityManager()->persist(new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN'));
        $this->entityManager()->persist(new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE'));
        $this->entityManager()->flush();

        $tester = $this->run_();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Incomplete', $tester->getDisplay());
    }

    public function testDetectsDuplicateLocaleRows(): void
    {
        $tuuid = Tuuid::generate();

        $this->entityManager()->persist(new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('One'));
        $this->entityManager()->persist(new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('Two'));
        $this->entityManager()->flush();

        $tester = $this->run_();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Duplicate', $tester->getDisplay());
    }

    /**
     * Negative proof for the STI double-counting bug: before
     * TranslatableEntityLocator returned only the root of an inheritance
     * hierarchy, this single standalone row was visited once through the
     * polymorphic StiRoot query AND again through a StiBook-specific query,
     * reporting the same anomaly twice ("2 ... anomalies" instead of "1").
     */
    public function testCountsEachStiRowOnceAcrossASubclassHierarchy(): void
    {
        $this->entityManager()->persist(new StiBook()->setName('Lonely')->setLocale('en_US'));
        $this->entityManager()->flush();

        $tester = $this->run_();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('1 translation linkage anomaly/anomalies detected.', $tester->getDisplay());
    }

    public function testReportsWhenNoTranslatableEntitiesExist(): void
    {
        $factory = self::createStub(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willReturn([]);

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getMetadataFactory')->willReturn($factory);

        $command = new TranslationDoctorCommand(
            $entityManager,
            new TranslatableEntityLocator($entityManager),
            self::LOCALES,
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No translatable entities', $tester->getDisplay());
    }

    /**
     * Negative proof for the "invented id" bug: before the grouped query
     * excluded NULL tuuids, TuuidType::convertToPHPValue() invented a fresh,
     * untraceable Tuuid for this row instead of reporting it by its real id
     * under a dedicated anomaly class.
     *
     * TranslatableTrait/TuuidType can never persist a literal NULL through
     * the entity itself, so the row is written with a raw DBAL insert that
     * bypasses Doctrine's Type conversion -- the same shape a pre-entity-layer
     * migration would leave behind.
     */
    public function testDetectsNullTuuidRow(): void
    {
        $id = $this->insertNullTuuidRow('en_US');

        $tester = $this->run_();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('NULL-tuuid', $tester->getDisplay());
        self::assertStringContainsString((string) $id, $tester->getDisplay());
        self::assertStringContainsString('1 translation linkage anomaly/anomalies detected.', $tester->getDisplay());
    }

    /**
     * --entity restricts the scan to one class: StiBook carries a genuine
     * standalone anomaly here, but it must not surface when only Scalar (a
     * fully healthy dataset) is named.
     */
    public function testEntityOptionRestrictsToOneClass(): void
    {
        $tuuid = Tuuid::generate();

        foreach (self::LOCALES as $locale) {
            $this->entityManager()->persist(new Scalar()->setTuuid($tuuid)->setLocale($locale)->setTitle($locale));
        }
        $this->entityManager()->persist(new StiBook()->setName('Lonely')->setLocale('en_US'));
        $this->entityManager()->flush();

        $tester = $this->run_(['--entity' => Scalar::class]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('correctly linked', $tester->getDisplay());
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
     * "mapped", but it has no table of its own to scan -- isTranslatableEntity()
     * must still reject it.
     */
    public function testEntityOptionRejectsAMappedSuperclass(): void
    {
        $tester = $this->run_(['--entity' => PrivateIdSuperclass::class]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('not a known translatable entity', $tester->getDisplay());
    }

    /**
     * --entity must accept a concrete subclass even though
     * TranslatableEntityLocator::locate() now names only the hierarchy root.
     */
    public function testEntityOptionAcceptsAConcreteSubclassNotNamedByTheLocator(): void
    {
        $this->entityManager()->persist(new StiBook()->setName('Lonely')->setLocale('en_US'));
        $this->entityManager()->flush();

        $tester = $this->run_(['--entity' => StiBook::class]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Standalone', $tester->getDisplay());
    }

    /**
     * Inserts a row with a literal database NULL tuuid, bypassing Doctrine's
     * Type conversion entirely (no $types argument to insert()) -- the same
     * way a raw SQL migration predating the entity layer would. Going through
     * the entity's own persist() path is not an option: TranslatableTrait
     * always assigns a Tuuid before flush, and TuuidType::convertToDatabaseValue()
     * invents one for a null PHP value too.
     */
    private function insertNullTuuidRow(string $locale): int
    {
        $metadata   = $this->entityManager()->getClassMetadata(NullableTuuidEntity::class);
        $connection = $this->entityManager()->getConnection();

        $connection->insert($metadata->getTableName(), [
            $metadata->getColumnName('locale') => $locale,
            $metadata->getColumnName('tuuid')  => null,
        ]);

        return (int) $connection->lastInsertId();
    }

    /**
     * @param array<string, string> $input
     */
    private function run_(array $input = []): CommandTester
    {
        $command = new TranslationDoctorCommand(
            $this->entityManager(),
            new TranslatableEntityLocator($this->entityManager()),
            self::LOCALES,
        );

        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }
}
