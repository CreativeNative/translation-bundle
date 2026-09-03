<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Tmi\TranslationBundle\Doctrine\EventListener\LocaleVariantRemovalListener;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\TranslatableRemover;
use Tmi\TranslationBundle\Fixtures\Entity\Removal\RemovableChild;
use Tmi\TranslationBundle\Fixtures\Entity\Removal\RemovableWithCallback;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class LocaleVariantRemovalListenerTest extends IntegrationTestCase
{
    public function testEnabledCascadesAPlainRemoveToSiblingLocaleVariants(): void
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

        $this->registerListener($em, true);

        // No TranslatableRemover call at all here -- a bare $em->remove()
        // must cascade on its own once the flag is on.
        $em->remove($en);
        $em->flush();
        $em->clear();

        foreach ($ids as $id) {
            self::assertNull($em->find(Scalar::class, $id));
        }
    }

    public function testDisabledLeavesSiblingLocaleVariantsInPlace(): void
    {
        $em    = $this->entityManager();
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');

        $em->persist($en);
        $em->persist($de);
        $em->flush();

        $enId = $en->getId();
        $deId = $de->getId();

        $this->registerListener($em, false);

        $em->remove($en);
        $em->flush();
        $em->clear();

        self::assertNull($em->find(Scalar::class, $enId));
        self::assertNotNull($em->find(Scalar::class, $deId));
    }

    public function testEnabledLeavesUnrelatedTranslatableRowsUntouchedWhenRemovingANonTranslatableEntity(): void
    {
        $em    = $this->entityManager();
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');

        // Not a TranslatableInterface implementer, and unrelated to the
        // Scalar rows above -- persisted standalone (its 'parent' is nullable).
        $child = new RemovableChild()->setName('Standalone');

        $em->persist($en);
        $em->persist($de);
        $em->persist($child);
        $em->flush();

        $enId    = $en->getId();
        $deId    = $de->getId();
        $childId = $child->getId();

        $this->registerListener($em, true);

        $em->remove($child);
        $em->flush();
        $em->clear();

        self::assertNull($em->find(RemovableChild::class, $childId));
        self::assertNotNull($em->find(Scalar::class, $enId));
        self::assertNotNull($em->find(Scalar::class, $deId));
    }

    public function testConsumerPreRemoveLifecycleCallbackFiresExactlyOncePerVariant(): void
    {
        $em    = $this->entityManager();
        $tuuid = Tuuid::generate();

        RemovableWithCallback::$preRemoveCallCount = 0;

        $en = new RemovableWithCallback()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de = new RemovableWithCallback()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $it = new RemovableWithCallback()->setTuuid($tuuid)->setLocale('it_IT')->setTitle('IT');

        $em->persist($en);
        $em->persist($de);
        $em->persist($it);
        $em->flush();

        $this->registerListener($em, true);

        $em->remove($en);
        $em->flush();

        self::assertSame(3, RemovableWithCallback::$preRemoveCallCount);
    }

    public function testEnabledStillRespectsTheRemoveSingleLocaleVariantExemption(): void
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
        $this->registerListener($em, true, $remover);

        $remover->removeSingleLocaleVariant($de);
        $em->flush();
        $em->clear();

        self::assertNull($em->find(Scalar::class, $deId));
        self::assertNotNull($em->find(Scalar::class, $enId));
        self::assertNotNull($em->find(Scalar::class, $itId));
    }

    public function testEnabledDoesNotReScheduleWhenRemoveAllLocaleVariantsTriggersTheListenerForEachVariant(): void
    {
        $realEntityManager = $this->entityManager();
        $tuuid             = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $it = new Scalar()->setTuuid($tuuid)->setLocale('it_IT')->setTitle('IT');

        $realEntityManager->persist($en);
        $realEntityManager->persist($de);
        $realEntityManager->persist($it);
        $realEntityManager->flush();

        // Reads go through the real (seeded) entity manager; writes go
        // through the mock below so remove() calls can be counted without
        // a real UnitOfWork dispatching preRemove on its own -- that
        // dispatch is simulated explicitly in the callback.
        $finder = new LocaleVariantFinder($realEntityManager);

        /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $mockEntityManager */
        $mockEntityManager = $this->createMock(EntityManagerInterface::class);

        $remover  = new TranslatableRemover($mockEntityManager, $finder);
        $listener = new LocaleVariantRemovalListener($remover, true);

        // removeAllLocaleVariants() sets the re-entrancy guard before its
        // loop, then calls $em->remove() once per variant -- each of those
        // calls is where a real event manager would dispatch preRemove
        // straight back into this listener. Without the guard check in
        // TranslatableRemover::cascadeFromPreRemove(), each of the three
        // calls below would re-query the finder and re-schedule the other
        // two variants, so remove() would be called far more than 3 times.
        $mockEntityManager->expects(self::exactly(3))
            ->method('remove')
            ->willReturnCallback(function (object $variant) use ($listener, $mockEntityManager): void {
                self::assertInstanceOf(TranslatableInterface::class, $variant);
                $listener->preRemove(new PreRemoveEventArgs($variant, $mockEntityManager));
            });

        $removed = $remover->removeAllLocaleVariants($en);

        self::assertCount(3, $removed);
    }

    private function registerListener(EntityManagerInterface $em, bool $enabled, TranslatableRemover|null $remover = null): void
    {
        $remover ??= new TranslatableRemover($em, new LocaleVariantFinder($em));

        $em->getEventManager()->addEventListener(Events::preRemove, new LocaleVariantRemovalListener($remover, $enabled));
    }
}
