<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Event\PostTranslateEvent;
use Tmi\TranslationBundle\Fixtures\Entity\SharedBackReference\SharedBackReferenceChild;
use Tmi\TranslationBundle\Fixtures\Entity\SharedBackReference\SharedBackReferenceParent;

/**
 * The 5.1 fix to BidirectionalManyToOneHandler: a child's #[SharedAmongstTranslations]
 * ManyToOne back-reference no longer makes the PARENT untranslatable. Reached through the
 * parent's OneToMany, the property is the child's FK back to the parent, and the only
 * correct value for it on the child's clone is the parent's clone.
 *
 * Negative proof: against 5.0.0 every test here dies in the handler's unconditional
 * `isShared()` throw ("::parent is a Bidirectional ManyToOne, it cannot be shared amongst
 * translations") before a single clone exists.
 */
final class SharedBackReferenceBidirectionalTest extends IntegrationTestCase
{
    public function testTranslatingTheParentClonesTheChildrenAndPointsThemAtTheParentClone(): void
    {
        $parent = new SharedBackReferenceParent()->setLocale('en_US')->setTitle('Itinerary EN');
        $parent->addChild(new SharedBackReferenceChild()->setLocale('en_US')->setTitle('Day 1 EN'));
        $parent->addChild(new SharedBackReferenceChild()->setLocale('en_US')->setTitle('Day 2 EN'));

        $this->entityManager()->persist($parent);
        $this->entityManager()->flush();

        $translated = $this->translator()->translate($parent, self::TARGET_LOCALE);

        self::assertInstanceOf(SharedBackReferenceParent::class, $translated);
        self::assertIsTranslation($parent, $translated, self::TARGET_LOCALE);
        self::assertCount(2, $translated->getChildren());

        foreach ($translated->getChildren() as $index => $child) {
            $source = $parent->getChildren()->get($index);
            self::assertInstanceOf(SharedBackReferenceChild::class, $source);

            self::assertNotSame($source, $child, 'The child must be a clone, not the source row');
            self::assertIsTranslation($source, $child, self::TARGET_LOCALE);
            self::assertSame($translated, $child->getParent(), 'The back-reference resolves to the parent CLONE');
        }

        foreach ($parent->getChildren() as $source) {
            self::assertSame($parent, $source->getParent(), 'The source children keep pointing at the source parent');
        }
    }

    public function testTheTranslatedGraphPersistsAsNewRowsLinkedToTheNewParentRow(): void
    {
        $parent = new SharedBackReferenceParent()->setLocale('en_US');
        $parent->addChild(new SharedBackReferenceChild()->setLocale('en_US'));

        $this->entityManager()->persist($parent);
        $this->entityManager()->flush();

        $translated = $this->translator()->translateAndPersist($parent, self::TARGET_LOCALE);
        $this->entityManager()->flush();
        self::assertInstanceOf(SharedBackReferenceParent::class, $translated);

        $translatedChild = $translated->getChildren()->first();
        self::assertInstanceOf(SharedBackReferenceChild::class, $translatedChild);

        $sourceChild = $parent->getChildren()->first();
        self::assertInstanceOf(SharedBackReferenceChild::class, $sourceChild);

        self::assertNotNull($translated->getId());
        self::assertNotSame($parent->getId(), $translated->getId());
        self::assertNotNull($translatedChild->getId());
        self::assertNotSame($sourceChild->getId(), $translatedChild->getId());

        $this->entityManager()->clear();

        $reloaded = $this->entityManager()->find(SharedBackReferenceChild::class, $translatedChild->getId());
        self::assertInstanceOf(SharedBackReferenceChild::class, $reloaded);
        self::assertNotNull($reloaded->getParent());
        self::assertSame($translated->getId(), $reloaded->getParent()->getId());
    }

    /**
     * A second translate() of the same parent finds the existing variant instead of
     * minting another: the cycle-guard and cache bookkeeping around the shared early
     * return in EntityTranslator::runHandlers() do not regress the ordinary
     * get-or-create behaviour of the parent itself.
     */
    public function testASecondTranslateReusesTheExistingParentVariant(): void
    {
        $parent = new SharedBackReferenceParent()->setLocale('en_US');
        $parent->addChild(new SharedBackReferenceChild()->setLocale('en_US'));

        $this->entityManager()->persist($parent);
        $this->entityManager()->flush();

        $first = $this->translator()->translateAndPersist($parent, self::TARGET_LOCALE);
        $this->entityManager()->flush();

        $second = $this->translator()->translate($parent, self::TARGET_LOCALE);

        self::assertSame($first, $second);
    }

    /**
     * Negative proof against 5.1: the child's clone came back through the shared early
     * return in EntityTranslator::runHandlers(), which dispatched no PostTranslateEvent
     * for it -- K children, one event (the parent's). recordTranslation() announces
     * every translation a handler produces, so listeners see 1 + K.
     */
    public function testEveryChildCloneIsAnnouncedByAPostTranslateEvent(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $parent = new SharedBackReferenceParent()->setLocale('en_US')->setTitle('Itinerary EN');
        $parent->addChild(new SharedBackReferenceChild()->setLocale('en_US')->setTitle('Day 1 EN'));
        $parent->addChild(new SharedBackReferenceChild()->setLocale('en_US')->setTitle('Day 2 EN'));
        $parent->addChild(new SharedBackReferenceChild()->setLocale('en_US')->setTitle('Day 3 EN'));

        $this->entityManager()->persist($parent);
        $this->entityManager()->flush();

        $events   = new \ArrayObject();
        $listener = static function (PostTranslateEvent $event) use ($events): void {
            $events->append($event);
        };
        $dispatcher->addListener(PostTranslateEvent::class, $listener);

        try {
            $translated = $this->translator()->translate($parent, self::TARGET_LOCALE);
        } finally {
            $dispatcher->removeListener(PostTranslateEvent::class, $listener);
        }

        self::assertInstanceOf(SharedBackReferenceParent::class, $translated);
        self::assertCount(1 + 3, $events, 'one event for the parent, one per child clone');

        $childEvents = 0;
        foreach ($events as $event) {
            $source     = $event->getSourceEntity();
            $translated = $event->getTranslatedEntity();
            self::assertInstanceOf(TranslatableInterface::class, $translated);
            self::assertSame(self::TARGET_LOCALE, $translated->getLocale());
            self::assertSame(self::TARGET_LOCALE, $event->getLocale());
            self::assertNotSame($source, $translated, 'the event carries a clone, never the source');

            if ($source instanceof SharedBackReferenceChild) {
                ++$childEvents;
                self::assertInstanceOf(SharedBackReferenceChild::class, $translated);
                self::assertTrue($parent->getChildren()->contains($source), 'the source is the parent\'s own child');
            }
        }
        self::assertSame(3, $childEvents);
    }

    /**
     * The same gap, seen from the cache: the child's clone was never stored, so
     * translating the child on its own after the parent cost a lookup query (see
     * QueryBudgetTest). Now the clone is a cache hit.
     */
    public function testTheChildCloneIsCachedUnderItsOwnTuuidAndTheTargetLocale(): void
    {
        $child  = new SharedBackReferenceChild()->setLocale('en_US')->setTitle('Day 1 EN');
        $parent = new SharedBackReferenceParent()->setLocale('en_US')->addChild($child);

        $this->entityManager()->persist($parent);
        $this->entityManager()->flush();

        $translated = $this->translator()->translate($parent, self::TARGET_LOCALE);
        self::assertInstanceOf(SharedBackReferenceParent::class, $translated);

        $cachedChild = $this->translationCache()->get($child->getTuuid()->getValue(), self::TARGET_LOCALE);
        self::assertSame($translated->getChildren()->first(), $cachedChild);
    }
}
