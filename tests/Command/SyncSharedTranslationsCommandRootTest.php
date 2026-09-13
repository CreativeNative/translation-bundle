<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tmi\TranslationBundle\Command\SyncSharedTranslationsCommand;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\SharedDriftScanner;
use Tmi\TranslationBundle\Doctrine\SharedValueSynchronizer;
use Tmi\TranslationBundle\Doctrine\TranslatableEntityLocator;
use Tmi\TranslationBundle\Fixtures\Entity\Root\Estate;
use Tmi\TranslationBundle\Fixtures\Entity\Root\EstateA;
use Tmi\TranslationBundle\Fixtures\Entity\Root\ListingA;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * `sync-shared` and a translation root reference (5.1): a group whose siblings point
 * at different roots FAILS the command in every mode and is never repaired by it --
 * the message points at adopt-root instead.
 */
#[CoversClass(SyncSharedTranslationsCommand::class)]
final class SyncSharedTranslationsCommandRootTest extends IntegrationTestCase
{
    public function testCheckFailsOnARootMismatchAndNamesAdoptRoot(): void
    {
        $this->seedMismatchedGroup();

        $tester = $this->run_(['--check' => true, '--entity' => Estate::class]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('1 translation root reference(s) differ between sibling rows', self::normalize($tester->getDisplay()));
        self::assertStringContainsString('tmi:translation:adopt-root --check', self::normalize($tester->getDisplay()));
        self::assertMatchesRegularExpression('/listing\s+1\s+1\s+no/', self::normalize($tester->getDisplay()), 'listed in the drift table as not writable');
    }

    public function testWriteModeLeavesTheForeignKeysAloneAndExitsNonZero(): void
    {
        [$deId, $deRootId] = $this->seedMismatchedGroup();

        $tester = $this->run_(['--entity' => Estate::class]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('differ between sibling rows and were left untouched', self::normalize($tester->getDisplay()));
        self::assertStringNotContainsString('translation(s) updated', $tester->getDisplay());

        $this->entityManager()->clear();
        $reloaded = new LocaleVariantFinder($this->entityManager())->withoutLocaleFilter(fn (): object|null => $this->entityManager()->find(EstateA::class, $deId));
        self::assertInstanceOf(EstateA::class, $reloaded);
        self::assertNotNull($reloaded->getListing());
        self::assertSame($deRootId, $reloaded->getListing()->getId());
    }

    public function testAGroupSharingItsRootIsInSync(): void
    {
        $tuuid = Tuuid::generate();
        $root  = new ListingA();
        $root->adoptTuuid($tuuid);
        $this->entityManager()->persist($root);
        $this->entityManager()->persist(new EstateA()->setTuuid($tuuid)->setLocale('en_US')->setListing($root));
        $this->entityManager()->persist(new EstateA()->setTuuid($tuuid)->setLocale('de_DE')->setListing($root));
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $tester = $this->run_(['--check' => true, '--entity' => Estate::class]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('already in sync', $tester->getDisplay());
    }

    /**
     * @return array{int, int} the de_DE row id and its root id
     */
    private function seedMismatchedGroup(): array
    {
        $tuuid  = Tuuid::generate();
        $rootEn = new ListingA();
        $rootEn->adoptTuuid($tuuid);
        $rootDe = new ListingA();
        $rootDe->adoptTuuid(Tuuid::generate());

        $de = new EstateA()->setTuuid($tuuid)->setLocale('de_DE')->setListing($rootDe);

        foreach ([$rootEn, $rootDe, new EstateA()->setTuuid($tuuid)->setLocale('en_US')->setListing($rootEn), $de] as $entity) {
            $this->entityManager()->persist($entity);
        }
        $this->entityManager()->flush();

        $deId     = $de->getId();
        $deRootId = $rootDe->getId();
        self::assertNotNull($deId);
        self::assertNotNull($deRootId);
        $this->entityManager()->clear();

        return [$deId, $deRootId];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function run_(array $input = []): CommandTester
    {
        $synchronizer = self::getContainer()->get('test.shared_value_synchronizer');
        self::assertInstanceOf(SharedValueSynchronizer::class, $synchronizer);

        $finder  = new LocaleVariantFinder($this->entityManager());
        $command = new SyncSharedTranslationsCommand(
            $this->entityManager(),
            new TranslatableEntityLocator($this->entityManager()),
            $finder,
            $synchronizer,
            new SharedDriftScanner($this->entityManager(), $finder, $synchronizer, 'en_US'),
            'en_US',
        );

        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }

    private static function normalize(string $display): string
    {
        return (string) preg_replace('/\s+/', ' ', $display);
    }
}
