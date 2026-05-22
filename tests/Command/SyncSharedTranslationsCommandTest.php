<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tmi\TranslationBundle\Command\SyncSharedTranslationsCommand;
use Tmi\TranslationBundle\Doctrine\TranslatableEntityLocator;
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
        $this->entityManager()->getFilters()->disable('tmi_translation_locale_filter');

        $entity = $this->entityManager()->find(Scalar::class, $id);
        self::assertInstanceOf(Scalar::class, $entity);

        return $entity->getShared();
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
