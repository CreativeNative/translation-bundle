<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine;

use PHPUnit\Framework\Attributes\CoversClass;
use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\SharedDriftScanner;
use Tmi\TranslationBundle\Doctrine\SharedValueSynchronizer;
use Tmi\TranslationBundle\Fixtures\Entity\Root\Article;
use Tmi\TranslationBundle\Fixtures\Entity\Root\Estate;
use Tmi\TranslationBundle\Fixtures\Entity\Root\EstateA;
use Tmi\TranslationBundle\Fixtures\Entity\Root\ListingA;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\SharedValueSyncReport;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * The shared-value machinery and a translation root reference (5.1): discovered as
 * shared, compared by identity, REPORTED when siblings disagree -- and never, in any
 * mode, written.
 *
 * Negative proof for the write refusal: with the `root` flag ignored, reconcile() would
 * fall through to the association write and re-point the sibling's FK at the source's
 * root -- testWriteModeLeavesBothForeignKeysUntouchedAndReportsTheMismatch asserts the
 * sibling's FK on the reloaded row.
 */
#[CoversClass(SharedValueSynchronizer::class)]
#[CoversClass(SharedValueSyncReport::class)]
#[CoversClass(SharedDriftScanner::class)]
final class SharedValueSynchronizerRootTest extends IntegrationTestCase
{
    public function testARootReferenceIsDiscoveredAsASharedAssociationFlaggedRoot(): void
    {
        $entries = $this->synchronizer()->sharedProperties(EstateA::class);
        $byPath  = array_column($entries, null, 'path');

        self::assertArrayHasKey('listing', $byPath);
        self::assertTrue($byPath['listing']['association']);
        self::assertTrue($byPath['listing']['root']);

        self::assertArrayHasKey('family', $byPath);
        self::assertFalse($byPath['family']['root']);

        // Without the marker, by type alone.
        $article = array_column($this->synchronizer()->sharedProperties(Article::class), null, 'path');
        self::assertTrue($article['root']['root']);
    }

    public function testSiblingsSharingTheRootAreNotDrift(): void
    {
        [$en, $de] = $this->seedGroup(sameRoot: true);

        $report = $this->synchronizer()->compare($en, $de);

        self::assertSame([], $report->changed());
        self::assertSame([], $report->readonlyDrift());
        self::assertSame([], $report->rootDrift());
    }

    public function testSiblingsWithDifferentRootsAreReportedAsRootDriftNotAsAChange(): void
    {
        [$en, $de] = $this->seedGroup(sameRoot: false);

        $report = $this->synchronizer()->compare($en, $de);

        self::assertSame([], $report->changed());
        self::assertFalse($report->hasChanges());
        self::assertSame(['listing'], $report->rootDrift());
    }

    public function testWriteModeLeavesBothForeignKeysUntouchedAndReportsTheMismatch(): void
    {
        [$en, $de] = $this->seedGroup(sameRoot: false);
        $enRoot    = $en->getListing();
        $deRoot    = $de->getListing();
        self::assertNotNull($enRoot);
        self::assertNotNull($deRoot);

        $report = $this->synchronizer()->sync($en, $de);
        $this->entityManager()->flush();

        self::assertSame(['listing'], $report->rootDrift());
        self::assertSame([], $report->changed());

        $this->entityManager()->clear();
        $reloadedDe = $this->find($de->getId());
        $reloadedEn = $this->find($en->getId());

        self::assertNotNull($reloadedDe->getListing());
        self::assertNotNull($reloadedEn->getListing());
        self::assertSame($deRoot->getId(), $reloadedDe->getListing()->getId(), 'the sibling keeps ITS root');
        self::assertSame($enRoot->getId(), $reloadedEn->getListing()->getId());
    }

    public function testSyncFromDoesNotCountARootMismatchAsAChangedSibling(): void
    {
        [$en] = $this->seedGroup(sameRoot: false);

        self::assertSame([], $this->synchronizer()->syncFrom($en));
    }

    public function testTheDriftScannerYieldsARootMismatchAsNotWritable(): void
    {
        $this->seedGroup(sameRoot: false);

        $finder  = new LocaleVariantFinder($this->entityManager());
        $scanner = new SharedDriftScanner($this->entityManager(), $finder, $this->synchronizer(), 'en_US');
        $drifts  = iterator_to_array($scanner->scan(Estate::class), false);

        self::assertCount(1, $drifts);
        self::assertSame('listing', $drifts[0]->propertyPath());
        self::assertTrue($drifts[0]->isReadonly());
        self::assertSame('de_DE', $drifts[0]->locale());
    }

    /**
     * @return array{Estate, Estate}
     */
    private function seedGroup(bool $sameRoot): array
    {
        $tuuid  = Tuuid::generate();
        $rootEn = new ListingA();
        $rootEn->adoptTuuid($tuuid);
        $rootDe = $rootEn;

        if (!$sameRoot) {
            $rootDe = new ListingA();
            $rootDe->adoptTuuid(Tuuid::generate());
        }

        $en = new EstateA()->setTuuid($tuuid)->setLocale('en_US')->setListing($rootEn);
        $de = new EstateA()->setTuuid($tuuid)->setLocale('de_DE')->setListing($rootDe);

        $this->entityManager()->persist($rootEn);
        $this->entityManager()->persist($rootDe);
        $this->entityManager()->persist($en);
        $this->entityManager()->persist($de);
        $this->entityManager()->flush();

        return [$en, $de];
    }

    private function find(int|null $id): EstateA
    {
        self::assertNotNull($id);

        $row = $this->entityManager()->getFilters()->isEnabled(LocaleFilter::NAME)
            ? new LocaleVariantFinder($this->entityManager())->withoutLocaleFilter(fn (): object|null => $this->entityManager()->find(EstateA::class, $id))
            : $this->entityManager()->find(EstateA::class, $id);

        self::assertInstanceOf(EstateA::class, $row);

        return $row;
    }

    private function synchronizer(): SharedValueSynchronizer
    {
        $synchronizer = self::getContainer()->get('test.shared_value_synchronizer');
        self::assertInstanceOf(SharedValueSynchronizer::class, $synchronizer);

        return $synchronizer;
    }
}
