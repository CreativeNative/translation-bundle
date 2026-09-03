<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\TranslatableRemover;
use Tmi\TranslationBundle\Fixtures\Entity\Removal\RemovableChild;
use Tmi\TranslationBundle\Fixtures\Entity\Removal\RemovableParent;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class TranslatableRemoverTest extends IntegrationTestCase
{
    public function testRemoveAllLocaleVariantsSchedulesEveryVariantAndReturnsThem(): void
    {
        $em    = $this->entityManager();
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $it = new Scalar()->setTuuid($tuuid)->setLocale('it_IT')->setTitle('IT');

        $em->persist($en);
        $em->persist($de);
        $em->persist($it);
        $em->flush();

        $ids = [$en->getId(), $de->getId(), $it->getId()];

        $remover = new TranslatableRemover($em, new LocaleVariantFinder($em));
        $removed = $remover->removeAllLocaleVariants($en);

        self::assertCount(3, $removed);
        self::assertTrue(in_array($en, $removed, true));
        self::assertTrue(in_array($de, $removed, true));
        self::assertTrue(in_array($it, $removed, true));

        $em->flush();
        $em->clear();

        foreach ($ids as $id) {
            self::assertNull($em->find(Scalar::class, $id));
        }
    }

    public function testRemoveAllLocaleVariantsAppendsThePassedEntityWhenTheFinderDoesNotIncludeIt(): void
    {
        $em    = $this->entityManager();
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');

        $em->persist($en);
        $em->persist($de);
        $em->flush();

        // Never persisted -- the finder's DB query cannot return it, so
        // removeAllLocaleVariants() must append it itself.
        $it = new Scalar()->setTuuid($tuuid)->setLocale('it_IT')->setTitle('IT');

        $remover = new TranslatableRemover($em, new LocaleVariantFinder($em));
        $removed = $remover->removeAllLocaleVariants($it);

        self::assertCount(3, $removed);
        self::assertTrue(in_array($it, $removed, true));
    }

    public function testRemoveAllLocaleVariantsWithNoSiblingsReturnsJustTheEntity(): void
    {
        $em = $this->entityManager();

        $en = new Scalar()->setLocale('en_US')->setTitle('Solo');
        $em->persist($en);
        $em->flush();

        $remover = new TranslatableRemover($em, new LocaleVariantFinder($em));

        self::assertSame([$en], $remover->removeAllLocaleVariants($en));
    }

    public function testRemoveAllLocaleVariantsStartingFromANonDefaultVariantRemovesAllOfThem(): void
    {
        $em    = $this->entityManager();
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $it = new Scalar()->setTuuid($tuuid)->setLocale('it_IT')->setTitle('IT');

        $em->persist($en);
        $em->persist($de);
        $em->persist($it);
        $em->flush();

        $ids = [$en->getId(), $de->getId(), $it->getId()];

        $remover = new TranslatableRemover($em, new LocaleVariantFinder($em));

        // Started from the German variant, not the default-locale one.
        $removed = $remover->removeAllLocaleVariants($de);
        self::assertCount(3, $removed);

        $em->flush();
        $em->clear();

        foreach ($ids as $id) {
            self::assertNull($em->find(Scalar::class, $id));
        }
    }

    public function testRemoveAllLocaleVariantsWorksWhileTheLocaleFilterIsActive(): void
    {
        $em    = $this->entityManager();
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $it = new Scalar()->setTuuid($tuuid)->setLocale('it_IT')->setTitle('IT');

        $em->persist($en);
        $em->persist($de);
        $em->persist($it);
        $em->flush();

        $ids = [$en->getId(), $de->getId(), $it->getId()];

        $filters = $em->getFilters();
        $filter  = $filters->getFilter(LocaleFilter::NAME);
        self::assertInstanceOf(LocaleFilter::class, $filter);
        $filter->setLocale('de_DE');
        self::assertTrue($filters->isEnabled(LocaleFilter::NAME));

        $remover = new TranslatableRemover($em, new LocaleVariantFinder($em));

        // Reproduces the incident this WP fixes: removing the currently
        // visible (filtered) variant must still find and remove its
        // siblings, not just the row the active filter shows.
        $removed = $remover->removeAllLocaleVariants($de);
        self::assertCount(3, $removed);

        $em->flush();
        $em->clear();

        foreach ($ids as $id) {
            self::assertNull($em->find(Scalar::class, $id));
        }

        self::assertTrue($filters->isEnabled(LocaleFilter::NAME));
    }

    public function testRemoveAllLocaleVariantsCascadesOrmRemovalForEveryVariant(): void
    {
        $em    = $this->entityManager();
        $tuuid = Tuuid::generate();

        $en = new RemovableParent()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $en->addChild(new RemovableChild()->setName('EN child'));

        $de = new RemovableParent()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $de->addChild(new RemovableChild()->setName('DE child'));

        $it = new RemovableParent()->setTuuid($tuuid)->setLocale('it_IT')->setTitle('IT');
        $it->addChild(new RemovableChild()->setName('IT child'));

        $em->persist($en);
        $em->persist($de);
        $em->persist($it);
        $em->flush();

        self::assertCount(3, $em->getRepository(RemovableParent::class)->findAll());
        self::assertCount(3, $em->getRepository(RemovableChild::class)->findAll());

        $remover = new TranslatableRemover($em, new LocaleVariantFinder($em));
        $removed = $remover->removeAllLocaleVariants($en);
        self::assertCount(3, $removed);

        $em->flush();

        // A bulk DQL DELETE would have bypassed the OneToMany cascade and
        // left every child orphaned; per-variant EntityManager::remove()
        // does not.
        self::assertCount(0, $em->getRepository(RemovableParent::class)->findAll());
        self::assertCount(0, $em->getRepository(RemovableChild::class)->findAll());
    }

    public function testRemoveAllLocaleVariantsClearsTheGuardSoALaterCascadeStillRuns(): void
    {
        $em    = $this->entityManager();
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');

        $em->persist($en);
        $em->persist($de);
        $em->flush();

        $finder = new LocaleVariantFinder($em);

        /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $mockEntityManager */
        $mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $mockEntityManager->expects(self::exactly(3))->method('remove');

        $remover = new TranslatableRemover($mockEntityManager, $finder);

        $removed = $remover->removeAllLocaleVariants($en);
        self::assertCount(2, $removed);

        // Nothing was actually deleted (remove() is mocked), so both rows
        // are still there for this to find. If the guard had leaked from
        // the call above, this would silently no-op instead of scheduling
        // $de for removal a second time -- the mock's exactly(3) would then
        // fail with only 2 calls.
        $remover->cascadeFromPreRemove($en);
    }

    public function testRemoveSingleLocaleVariantRemovesOnlyThatVariant(): void
    {
        $em    = $this->entityManager();
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $it = new Scalar()->setTuuid($tuuid)->setLocale('it_IT')->setTitle('IT');

        $em->persist($en);
        $em->persist($de);
        $em->persist($it);
        $em->flush();

        $enId = $en->getId();
        $deId = $de->getId();
        $itId = $it->getId();

        $remover = new TranslatableRemover($em, new LocaleVariantFinder($em));
        $remover->removeSingleLocaleVariant($de);

        $em->flush();
        $em->clear();

        self::assertNull($em->find(Scalar::class, $deId));
        self::assertNotNull($em->find(Scalar::class, $enId));
        self::assertNotNull($em->find(Scalar::class, $itId));
    }

    public function testRemoveSingleLocaleVariantExemptsTheEntityFromCascadeDuringRemove(): void
    {
        $em    = $this->entityManager();
        $tuuid = Tuuid::generate();

        $entity  = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $sibling = new Scalar()->setTuuid($tuuid)->setLocale('it_IT')->setTitle('IT');

        $em->persist($entity);
        $em->persist($sibling);
        $em->flush();

        $finder = new LocaleVariantFinder($em);

        /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $mockEntityManager */
        $mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $remover           = new TranslatableRemover($mockEntityManager, $finder);

        $mockEntityManager->expects(self::once())
            ->method('remove')
            ->with($entity)
            ->willReturnCallback(function () use ($remover, $entity): void {
                // Simulates the (later-WP) listener firing synchronously
                // from inside remove() -- preRemove runs before remove()
                // returns -- while the exemption still holds. Without it,
                // this would find $sibling and remove() it too, breaking
                // the mock's expectation of exactly one call.
                $remover->cascadeFromPreRemove($entity);
            });

        $remover->removeSingleLocaleVariant($entity);
    }

    public function testCascadeFromPreRemoveSchedulesSiblingsButNotTheEntityItself(): void
    {
        $em    = $this->entityManager();
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $it = new Scalar()->setTuuid($tuuid)->setLocale('it_IT')->setTitle('IT');

        $em->persist($en);
        $em->persist($de);
        $em->persist($it);
        $em->flush();

        $remover = new TranslatableRemover($em, new LocaleVariantFinder($em));
        $remover->cascadeFromPreRemove($de);

        $uow = $em->getUnitOfWork();
        self::assertTrue($uow->isScheduledForDelete($en));
        self::assertTrue($uow->isScheduledForDelete($it));
        self::assertFalse($uow->isScheduledForDelete($de));
    }

    public function testCascadeFromPreRemoveIsANoOpWhenReenteredForTheSameTuuid(): void
    {
        $em    = $this->entityManager();
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');

        $em->persist($en);
        $em->persist($de);
        $em->flush();

        $finder = new LocaleVariantFinder($em);

        /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $mockEntityManager */
        $mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $remover           = new TranslatableRemover($mockEntityManager, $finder);

        $mockEntityManager->expects(self::once())
            ->method('remove')
            ->with($de)
            ->willReturnCallback(function () use ($remover, $en): void {
                // Simulates the future listener re-entering for the same
                // Tuuid while the outer call is still in progress -- must
                // be a no-op, or $de would be scheduled twice here.
                $remover->cascadeFromPreRemove($en);
            });

        $remover->cascadeFromPreRemove($en);
    }

    /**
     * Negative-proof / incident reproduction: a hand-rolled "delete every
     * variant" built on a locale-filtered findBy() only ever sees -- and
     * only ever removes -- the current-locale row, leaving the sibling
     * locale variants of the same Tuuid online. This is the exact incident
     * TranslatableRemover (exercised throughout the rest of this file and
     * {@see LocaleVariantFinderTest}) fixes.
     */
    public function testNaiveFindByAndRemoveLeavesSiblingsOnline(): void
    {
        $em    = $this->entityManager();
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $it = new Scalar()->setTuuid($tuuid)->setLocale('it_IT')->setTitle('IT');

        $em->persist($en);
        $em->persist($de);
        $em->persist($it);
        $em->flush();

        $filters = $em->getFilters();
        $filter  = $filters->getFilter(LocaleFilter::NAME);
        self::assertInstanceOf(LocaleFilter::class, $filter);
        $filter->setLocale('en_US');

        foreach ($em->getRepository(Scalar::class)->findBy(['tuuid' => (string) $tuuid]) as $result) {
            $em->remove($result);
        }
        $em->flush();
        $em->clear();

        $remaining = new LocaleVariantFinder($em)->findAllLocaleVariants(Scalar::class, $tuuid);

        self::assertCount(2, $remaining);
        self::assertArrayHasKey('de_DE', $remaining);
        self::assertArrayHasKey('it_IT', $remaining);
    }
}
