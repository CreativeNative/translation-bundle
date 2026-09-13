<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Command;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tmi\TranslationBundle\Command\AdoptRootCommand;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;
use Tmi\TranslationBundle\Doctrine\Root\RootAdopterInterface;
use Tmi\TranslationBundle\Doctrine\Root\RootAdopterRegistry;
use Tmi\TranslationBundle\Doctrine\Root\RootCheckAggregator;
use Tmi\TranslationBundle\Doctrine\Root\TuuidOrphanCounterInterface;
use Tmi\TranslationBundle\Doctrine\TranslatableEntityLocator;
use Tmi\TranslationBundle\Exception\RootAdoptionException;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\PrivateIdSuperclass;
use Tmi\TranslationBundle\Fixtures\Entity\Root\Estate;
use Tmi\TranslationBundle\Fixtures\Entity\Root\EstateA;
use Tmi\TranslationBundle\Fixtures\Entity\Root\EstateB;
use Tmi\TranslationBundle\Fixtures\Entity\Root\Listing;
use Tmi\TranslationBundle\Fixtures\Entity\Root\ListingA;
use Tmi\TranslationBundle\Fixtures\Entity\Root\ListingB;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\Test\Support\Root\EstateRootAdopter;
use Tmi\TranslationBundle\Test\Support\Root\OverridableRootAdopter;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * `tmi:translation:adopt-root` (5.1): whole-group classification before the first
 * write, group atomicity, self-healing, and the CI-grade --check.
 */
#[CoversClass(AdoptRootCommand::class)]
final class AdoptRootCommandTest extends IntegrationTestCase
{
    // ------------------------------------------------------------------
    // Nothing declared
    // ------------------------------------------------------------------

    public function testWithoutAnyAdopterTheCommandSucceedsAndSaysSo(): void
    {
        $tester = $this->run_([], adopters: []);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No entity declares a translation root.', $tester->getDisplay());
    }

    // ------------------------------------------------------------------
    // Write mode: new / complete / partial
    // ------------------------------------------------------------------

    public function testANewGroupGetsOneRootCarryingTheGroupsTuuid(): void
    {
        $tuuid = $this->seedGroup(EstateA::class, ['en_US', 'de_DE', 'it_IT'], family: 'villa');

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 group(s) adopted', $tester->getDisplay());
        self::assertMatchesRegularExpression('/new\s+1/', self::normalize($tester->getDisplay()));

        $rows  = $this->rowsOf($tuuid);
        $roots = [];

        foreach ($rows as $row) {
            $root = $row->getListing();
            self::assertInstanceOf(ListingA::class, $root);
            self::assertSame($tuuid, (string) $root->getTuuid(), 'the root ADOPTS the group\'s identity');
            self::assertSame('villa', $root->getFamily(), 'createRootFor() saw the rows');
            $roots[$root->getId() ?? 0] = true;
        }

        self::assertCount(3, $rows);
        self::assertCount(1, $roots, 'one root for the whole group');
        self::assertSame(1, $this->countRoots());
    }

    public function testASecondRunIsANoOp(): void
    {
        $this->seedGroup(EstateA::class, ['en_US', 'de_DE']);
        $this->run_();

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('0 group(s) adopted', $tester->getDisplay());
        self::assertMatchesRegularExpression('/complete\s+1/', self::normalize($tester->getDisplay()));
        self::assertSame(1, $this->countRoots());
    }

    public function testASingleRowGroupIsAdoptedLikeAnyOther(): void
    {
        $tuuid = $this->seedGroup(EstateB::class, ['en_US']);

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $rows = $this->rowsOf($tuuid);
        self::assertCount(1, $rows);
        self::assertInstanceOf(ListingB::class, $rows['en_US']->getListing());
    }

    public function testAPartialGroupFailsCheckAndSelfHealsInWriteModeOntoTheExistingRoot(): void
    {
        $root  = $this->root(ListingA::class);
        $tuuid = (string) $root->getTuuid();
        $this->seedGroup(EstateA::class, ['en_US', 'de_DE'], tuuid: $tuuid, rootByLocale: ['en_US' => $root]);

        $check = $this->run_(['--check' => true]);
        self::assertSame(Command::FAILURE, $check->getStatusCode());
        self::assertMatchesRegularExpression('/partial\s+1/', self::normalize($check->getDisplay()));
        self::assertStringContainsString('1 of 2 row(s) attached', $check->getDisplay());

        $write = $this->run_();
        self::assertSame(Command::SUCCESS, $write->getStatusCode());
        self::assertStringContainsString('1 group(s) adopted', $write->getDisplay());

        foreach ($this->rowsOf($tuuid) as $row) {
            self::assertNotNull($row->getListing());
            self::assertSame($root->getId(), $row->getListing()->getId(), 'healed onto the EXISTING root');
        }

        self::assertSame(1, $this->countRoots(), 'no second root was minted');
        self::assertSame(Command::SUCCESS, $this->run_(['--check' => true])->getStatusCode());
    }

    public function testMoreGroupsThanOneBatchAreAllAdopted(): void
    {
        $tuuids = [];
        for ($i = 0; $i < 12; ++$i) {
            $tuuids[] = $this->seedGroup(EstateA::class, ['en_US', 'de_DE']);
        }

        $tester = $this->run_();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('12 group(s) adopted', $tester->getDisplay());
        self::assertSame(12, $this->countRoots());

        foreach ($tuuids as $tuuid) {
            foreach ($this->rowsOf($tuuid) as $row) {
                self::assertNotNull($row->getListing(), 'a group on a batch boundary (the finder\'s lookahead row) is adopted too');
            }
        }
    }

    // ------------------------------------------------------------------
    // Write mode refuses: drift / ambiguous / mismatched -- before the first write
    // ------------------------------------------------------------------

    public function testADriftedGroupAbortsTheRunBeforeAnyWrite(): void
    {
        $stranger = $this->root(ListingA::class);
        $drifted  = $this->seedGroup(EstateA::class, ['en_US', 'de_DE'], rootByLocale: ['en_US' => $stranger, 'de_DE' => $stranger]);
        $pending  = $this->seedGroup(EstateA::class, ['en_US']);

        $tester = $this->run_();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('aborting before the first write', self::normalize($tester->getDisplay()));
        self::assertMatchesRegularExpression('/drift\s+1/', self::normalize($tester->getDisplay()));
        self::assertStringContainsString('root tuuid '.$stranger->getTuuid(), $tester->getDisplay());
        self::assertStringContainsString($drifted, $tester->getDisplay());

        self::assertNull($this->rowsOf($pending)['en_US']->getListing(), 'the NEW group was not adopted');
        self::assertSame(1, $this->countRoots());
    }

    public function testARootOfTheWrongClassIsDrift(): void
    {
        $wrong = $this->root(ListingB::class);
        $this->seedGroup(EstateA::class, ['en_US'], tuuid: (string) $wrong->getTuuid(), rootByLocale: ['en_US' => $wrong]);

        $tester = $this->run_(['--check' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString(\sprintf('root is %s, rows imply %s', ListingB::class, ListingA::class), $tester->getDisplay());
    }

    public function testARootWithoutAnIdentityIsDrift(): void
    {
        $this->seedGroup(EstateA::class, ['en_US']);

        $adopter = new OverridableRootAdopter(new EstateRootAdopter(), getRoot: static fn (): TranslationRootInterface => new ListingA());
        $tester  = $this->run_(['--check' => true], adopters: [$adopter]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('root tuuid none', $tester->getDisplay());
    }

    public function testTwoDistinctRootsInsideOneGroupAreAmbiguous(): void
    {
        $tuuid = Tuuid::generate();
        $one   = $this->root(ListingA::class, $tuuid);
        $two   = $this->root(ListingA::class);
        $this->seedGroup(EstateA::class, ['en_US', 'de_DE'], tuuid: (string) $tuuid, rootByLocale: ['en_US' => $one, 'de_DE' => $two]);

        $tester = $this->run_();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertMatchesRegularExpression('/ambiguous\s+1/', self::normalize($tester->getDisplay()));
        self::assertStringContainsString('2 distinct roots', $tester->getDisplay());
    }

    public function testRowsDisagreeingOnTheCoherenceKeyAreMismatchedAndNeverAdopted(): void
    {
        $tuuid = Tuuid::generate();
        $this->persistRow(new EstateA()->setTuuid($tuuid)->setLocale('en_US')->setFamily('villa'));
        $this->persistRow(new EstateA()->setTuuid($tuuid)->setLocale('de_DE')->setFamily('apartment'));
        $this->entityManager()->clear();

        $tester = $this->run_();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertMatchesRegularExpression('/mismatched\s+1/', self::normalize($tester->getDisplay()));
        self::assertStringContainsString('coherence keys: "apartment", "villa"', self::normalize($tester->getDisplay()));
        self::assertSame(0, $this->countRoots());
    }

    public function testRowsSpanningTwoLeavesAreMismatchedOnTheRootClass(): void
    {
        $tuuid = Tuuid::generate();
        $this->persistRow(new EstateA()->setTuuid($tuuid)->setLocale('en_US'));
        $this->persistRow(new EstateB()->setTuuid($tuuid)->setLocale('de_DE'));
        $this->entityManager()->clear();

        $tester = $this->run_(['--check' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString(\sprintf('root classes: %s, %s', ListingA::class, ListingB::class), $tester->getDisplay());
    }

    // ------------------------------------------------------------------
    // The factory contract and group atomicity
    // ------------------------------------------------------------------

    public function testAFactoryReturningAMintedRootIsRefusedAndNothingIsPersisted(): void
    {
        $this->seedGroup(EstateA::class, ['en_US']);

        $adopter = new OverridableRootAdopter(new EstateRootAdopter(), createRootFor: static function (): TranslationRootInterface {
            $root = new ListingA();
            $root->mintTuuid();

            return $root;
        });

        try {
            $this->run_([], adopters: [$adopter]);
            self::fail('Expected RootAdoptionException');
        } catch (RootAdoptionException $e) {
            self::assertStringContainsString('already carries a tuuid', $e->getMessage());
        }

        $this->entityManager()->clear();
        self::assertSame(0, $this->countRoots());
    }

    public function testAFactoryReturningTheWrongRootClassIsRefused(): void
    {
        $this->seedGroup(EstateA::class, ['en_US']);

        $adopter = new OverridableRootAdopter(new EstateRootAdopter(), createRootFor: static fn (): TranslationRootInterface => new ListingB());

        try {
            $this->run_([], adopters: [$adopter]);
            self::fail('Expected RootAdoptionException');
        } catch (RootAdoptionException $e) {
            self::assertStringContainsString(\sprintf('returned a %s', ListingB::class), $e->getMessage());
            self::assertStringContainsString(\sprintf('rows imply %s', ListingA::class), $e->getMessage());
        }

        $this->entityManager()->clear();
        self::assertSame(0, $this->countRoots());
    }

    public function testAnInterruptionAfterPersistingTheRootLeavesNoHalfAttachedGroup(): void
    {
        $tuuid = $this->seedGroup(EstateA::class, ['en_US', 'de_DE']);

        $calls   = 0;
        $adopter = new OverridableRootAdopter(new EstateRootAdopter(), attach: static function (TranslatableInterface $row, TranslationRootInterface $root, RootAdopterInterface $inner) use (&$calls): void {
            if (2 === ++$calls) {
                throw new \RuntimeException('connection lost');
            }

            $inner->attach($row, $root);
        });

        try {
            $this->run_([], adopters: [$adopter]);
            self::fail('Expected the interruption to propagate');
        } catch (\RuntimeException $e) {
            self::assertSame('connection lost', $e->getMessage());
        }

        $this->entityManager()->clear();
        self::assertSame(0, $this->countRoots(), 'the persisted root was never flushed');

        foreach ($this->rowsOf($tuuid) as $row) {
            self::assertNull($row->getListing(), 'no row of the group was attached');
        }
    }

    // ------------------------------------------------------------------
    // --dry-run / --check
    // ------------------------------------------------------------------

    public function testDryRunClassifiesAndWritesNothing(): void
    {
        $tuuid = $this->seedGroup(EstateA::class, ['en_US', 'de_DE']);

        $tester = $this->run_(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('(dry run)', $tester->getDisplay());
        self::assertStringContainsString('Dry run complete -- nothing written.', $tester->getDisplay());
        self::assertMatchesRegularExpression('/new\s+1/', self::normalize($tester->getDisplay()));
        self::assertSame(0, $this->countRoots());
        self::assertNull($this->rowsOf($tuuid)['en_US']->getListing());
    }

    public function testCheckFailsOnANewGroupAndPassesOnceAdopted(): void
    {
        $this->seedGroup(EstateA::class, ['en_US', 'de_DE']);

        $before = $this->run_(['--check' => true]);
        self::assertSame(Command::FAILURE, $before->getStatusCode());
        self::assertStringContainsString('The translation root invariant does not hold', $before->getDisplay());
        self::assertSame(0, $this->countRoots(), '--check never writes');

        $this->run_();

        $after = $this->run_(['--check' => true]);
        self::assertSame(Command::SUCCESS, $after->getStatusCode());
        self::assertStringContainsString('Every group has its root, every root has rows, every orphan counter is 0.', $after->getDisplay());
    }

    public function testCheckOnAnEmptyTablePasses(): void
    {
        $tester = $this->run_(['--check' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertMatchesRegularExpression('/complete\s+0/', self::normalize($tester->getDisplay()), 'every classification is printed, at 0 too');
    }

    public function testCheckFailsOnARootWithoutRows(): void
    {
        $root = $this->root(ListingA::class);
        $this->seedGroup(EstateA::class, ['en_US'], tuuid: (string) $root->getTuuid(), rootByLocale: ['en_US' => $root]);
        $this->root(ListingB::class);
        $this->entityManager()->clear();

        $tester = $this->run_(['--check' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertMatchesRegularExpression('/'.preg_quote(Listing::class, '/').'\s+'.preg_quote(Estate::class.'::$listing', '/').'\s+1/', self::normalize($tester->getDisplay()));
        self::assertMatchesRegularExpression('/complete\s+1/', self::normalize($tester->getDisplay()), 'the group itself is fine');
    }

    public function testMoreThanTwentyAnomalyGroupsAreCutOffWithACount(): void
    {
        for ($i = 0; $i < 21; ++$i) {
            $this->seedGroup(EstateA::class, ['en_US']);
        }

        $tester = $this->run_(['--check' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('… and 1 more new group(s).', $tester->getDisplay());
        self::assertMatchesRegularExpression('/new\s+21/', self::normalize($tester->getDisplay()));
    }

    // ------------------------------------------------------------------
    // Orphan counters
    // ------------------------------------------------------------------

    public function testWithoutCountersTheReportSaysSo(): void
    {
        $tester = $this->run_(['--check' => true]);

        self::assertStringContainsString('No tmi_translation.tuuid_orphan_counter registered.', $tester->getDisplay());
    }

    public function testEveryCounterIsPrintedAtZeroTooAndANonZeroOneFailsTheCheck(): void
    {
        $clean = $this->run_(['--check' => true], counters: [$this->counter('rental_photo.tuuid', 0), $this->counter('translation_review.tuuid', 0)]);

        self::assertSame(Command::SUCCESS, $clean->getStatusCode());
        self::assertMatchesRegularExpression('/rental_photo\.tuuid\s+0\s+OK/', self::normalize($clean->getDisplay()));
        self::assertMatchesRegularExpression('/translation_review\.tuuid\s+0\s+OK/', self::normalize($clean->getDisplay()));

        $dirty = $this->run_(['--check' => true], counters: [$this->counter('rental_photo.tuuid', 0), $this->counter('translation_review.tuuid', 44)]);

        self::assertSame(Command::FAILURE, $dirty->getStatusCode());
        self::assertMatchesRegularExpression('/translation_review\.tuuid\s+44\s+ORPHANS/', self::normalize($dirty->getDisplay()));
    }

    public function testAThrowingCounterShowsErrorWithTheExceptionClassAndFailsTheCheck(): void
    {
        $tester = $this->run_(['--check' => true], counters: [$this->counter('broken.tuuid', 0, throws: true)]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertMatchesRegularExpression('/broken\.tuuid\s+ERROR\s+'.preg_quote(\DomainException::class, '/').'/', self::normalize($tester->getDisplay()));
    }

    public function testAThrowingRootsWithoutRowsQueryShowsErrorAndFailsTheCheck(): void
    {
        $this->seedGroup(EstateA::class, ['en_US']);
        $this->run_();

        $broken = self::createStub(EntityManagerInterface::class);
        $broken->method('createQuery')->willThrowException(new \DomainException('no such table'));
        $checks = new RootCheckAggregator($broken, new LocaleVariantFinder($this->entityManager()));

        $tester = $this->run_(['--check' => true], checks: $checks);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('ERROR ('.\DomainException::class.')', $tester->getDisplay());
    }

    public function testCountersAreInformationalInWriteMode(): void
    {
        $tester = $this->run_([], counters: [$this->counter('rental_photo.tuuid', 160)]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), 'write mode adopted what it could; the counter is printed, not gated');
        self::assertMatchesRegularExpression('/rental_photo\.tuuid\s+160\s+ORPHANS/', self::normalize($tester->getDisplay()));
    }

    // ------------------------------------------------------------------
    // --entity
    // ------------------------------------------------------------------

    public function testEntityOptionWithAConcreteLeafStreamsTheWholeHierarchy(): void
    {
        $a = $this->seedGroup(EstateA::class, ['en_US']);
        $b = $this->seedGroup(EstateB::class, ['en_US']);

        $tester = $this->run_(['--entity' => EstateA::class]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('2 group(s) adopted', $tester->getDisplay());
        self::assertInstanceOf(ListingA::class, $this->rowsOf($a)['en_US']->getListing());
        self::assertInstanceOf(ListingB::class, $this->rowsOf($b)['en_US']->getListing(), 'the sibling leaf was seen in the same run');
    }

    public function testEntityOptionRejectsAnUnknownClass(): void
    {
        $tester = $this->run_(['--entity' => 'App\\Entity\\DoesNotExist']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('not a known translatable entity', self::normalize($tester->getDisplay()));
    }

    public function testEntityOptionRejectsAnUnmappedClassAMappedSuperclassAndANonTranslatableEntity(): void
    {
        foreach ([\stdClass::class, PrivateIdSuperclass::class, ListingA::class] as $class) {
            $tester = $this->run_(['--entity' => $class]);

            self::assertSame(Command::FAILURE, $tester->getStatusCode(), $class);
            self::assertStringContainsString('not a known translatable entity', self::normalize($tester->getDisplay()), $class);
        }
    }

    public function testEntityOptionRejectsATranslatableWithoutAnAdopter(): void
    {
        $tester = $this->run_(['--entity' => Scalar::class]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('declares no translation root', self::normalize($tester->getDisplay()));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed>                  $input
     * @param list<RootAdopterInterface>|null       $adopters null = the reference Estate adopter
     * @param list<TuuidOrphanCounterInterface>     $counters
     */
    private function run_(array $input = [], array|null $adopters = null, array $counters = [], RootCheckAggregator|null $checks = null): CommandTester
    {
        $registry = new RootAdopterRegistry();

        foreach ($adopters ?? [new EstateRootAdopter()] as $adopter) {
            $registry->addAdopter($adopter, $adopter->getTranslatableClass());
        }

        $finder = new LocaleVariantFinder($this->entityManager());
        $checks ??= new RootCheckAggregator($this->entityManager(), $finder);

        foreach ($counters as $counter) {
            $checks->addCounter($counter);
        }

        $tester = new CommandTester(new AdoptRootCommand($this->entityManager(), $finder, $registry, $checks, $this->attributeHelper(), new TranslatableEntityLocator($this->entityManager())));
        $tester->execute($input);

        return $tester;
    }

    /**
     * Persists one locale variant per locale, all sharing $tuuid (fresh unless given),
     * optionally already attached to a root per locale. Clears the EntityManager so the
     * command hydrates from the database, as a cold process would.
     *
     * @param class-string<Estate>            $leaf
     * @param list<string>                    $locales
     * @param array<string, Listing>          $rootByLocale
     *
     * @return string the group's tuuid
     */
    private function seedGroup(string $leaf, array $locales, string|null $tuuid = null, array $rootByLocale = [], string $family = 'default'): string
    {
        $tuuid ??= (string) Tuuid::generate();

        foreach ($locales as $locale) {
            $row = new $leaf()->setTuuid(new Tuuid($tuuid))->setLocale($locale)->setFamily($family)->setTitle('Title '.$locale);

            if (isset($rootByLocale[$locale])) {
                $root = $rootByLocale[$locale];
                $row->setListing($this->entityManager()->contains($root) ? $root : $this->reattach($root));
            }

            $this->entityManager()->persist($row);
        }

        $this->entityManager()->flush();
        $this->entityManager()->clear();

        return $tuuid;
    }

    private function reattach(Listing $root): Listing
    {
        $id = $root->getId();
        self::assertNotNull($id);
        $managed = $this->entityManager()->find(Listing::class, $id);
        self::assertInstanceOf(Listing::class, $managed);

        return $managed;
    }

    private function persistRow(Estate $row): void
    {
        $this->entityManager()->persist($row);
        $this->entityManager()->flush();
    }

    /**
     * @param class-string<Listing> $class
     */
    private function root(string $class, Tuuid|null $tuuid = null): Listing
    {
        $root = new $class();
        $root->adoptTuuid($tuuid ?? Tuuid::generate());
        $this->entityManager()->persist($root);
        $this->entityManager()->flush();

        return $root;
    }

    /**
     * @return array<string, Estate> by locale, freshly loaded
     */
    private function rowsOf(string $tuuid): array
    {
        $this->entityManager()->clear();

        /** @var array<string, Estate> $rows */
        $rows = new LocaleVariantFinder($this->entityManager())->findAllLocaleVariants(Estate::class, new Tuuid($tuuid));

        return $rows;
    }

    private function countRoots(): int
    {
        /** @var int|string $count */
        $count = $this->entityManager()->createQuery(\sprintf('SELECT COUNT(r) FROM %s r', Listing::class))->getSingleScalarResult();

        return (int) $count;
    }

    private function counter(string $name, int $count, bool $throws = false): TuuidOrphanCounterInterface
    {
        return new class($name, $count, $throws) implements TuuidOrphanCounterInterface {
            public function __construct(private readonly string $name, private readonly int $count, private readonly bool $throws)
            {
            }

            #[\Override]
            public function getName(): string
            {
                return $this->name;
            }

            #[\Override]
            public function countOrphans(): int
            {
                if ($this->throws) {
                    throw new \DomainException('table gone');
                }

                return $this->count;
            }
        };
    }

    private static function normalize(string $display): string
    {
        return (string) preg_replace('/\s+/', ' ', $display);
    }
}
