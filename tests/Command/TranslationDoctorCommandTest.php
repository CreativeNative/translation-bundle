<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tmi\TranslationBundle\Command\TranslationDoctorCommand;
use Tmi\TranslationBundle\Doctrine\TranslatableEntityLocator;
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

    private function run_(): CommandTester
    {
        $command = new TranslationDoctorCommand(
            $this->entityManager(),
            new TranslatableEntityLocator($this->entityManager()),
            self::LOCALES,
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        return $tester;
    }
}
