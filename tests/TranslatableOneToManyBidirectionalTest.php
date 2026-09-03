<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalParent;

final class TranslatableOneToManyBidirectionalTest extends IntegrationTestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateBidirectionalOneToMany(): void
    {
        $children = new ArrayCollection([
            new TranslatableManyToOneBidirectionalChild()->setLocale('de_DE'),
            new TranslatableManyToOneBidirectionalChild()->setLocale('de_DE'),
            new TranslatableManyToOneBidirectionalChild()->setLocale('de_DE'),
        ]);

        $parent = new TranslatableOneToManyBidirectionalParent()
            ->setLocale('de_DE');

        // Persist children first and assign parent
        foreach ($children as $child) {
            $child->setParentSimple($parent);
            $this->entityManager()->persist($child);
        }

        $parent->setSimpleChildren($children);
        $this->entityManager()->persist($parent);
        $this->entityManager()->flush();

        $parentTranslation = $this->translator()->translate($parent, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $parentTranslation);

        $this->entityManager()->persist($parentTranslation);
        $this->entityManager()->flush();

        $firstTranslatedChild = $parentTranslation->getSimpleChildren()->first();
        self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $firstTranslatedChild);
        self::assertEquals(self::TARGET_LOCALE, $firstTranslatedChild->getLocale());

        $firstOriginalChild = $parent->getSimpleChildren()->first();
        self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $firstOriginalChild);

        $firstTranslatedChildAgain = $parentTranslation->getSimpleChildren()->first();
        self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $firstTranslatedChildAgain);
        self::assertEquals(
            $firstOriginalChild->getTuuid(),
            $firstTranslatedChildAgain->getTuuid(),
        );
    }

    /**
     * The OneToMany handler only runs when the source locale actually differs from the
     * target. Its supports() used to test the data for TranslatableInterface, but the data
     * of a OneToMany property is the children Collection, so the handler never ran and the
     * translated parent silently kept the source's untranslated children.
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testChildrenAreTranslatedAndTheSourceCollectionIsLeftAlone(): void
    {
        $parent = new TranslatableOneToManyBidirectionalParent()->setLocale('en_US');

        $children = new ArrayCollection([
            new TranslatableManyToOneBidirectionalChild()->setLocale('en_US'),
            new TranslatableManyToOneBidirectionalChild()->setLocale('en_US'),
        ]);

        foreach ($children as $child) {
            $child->setParentSimple($parent);
            $this->entityManager()->persist($child);
        }

        $parent->setSimpleChildren($children);
        $this->entityManager()->persist($parent);
        $this->entityManager()->flush();

        $parentTranslation = $this->translator()->translate($parent, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $parentTranslation);

        $this->entityManager()->persist($parentTranslation);
        $this->entityManager()->flush();

        $translatedChildren = $parentTranslation->getSimpleChildren();
        self::assertCount(2, $translatedChildren);
        self::assertNotSame($parent->getSimpleChildren(), $translatedChildren);

        foreach ($translatedChildren as $translatedChild) {
            self::assertSame(self::TARGET_LOCALE, $translatedChild->getLocale());
            self::assertSame($parentTranslation, $translatedChild->getParentSimple());
        }

        // The source parent still owns its own untranslated children
        self::assertCount(2, $parent->getSimpleChildren());
        foreach ($parent->getSimpleChildren() as $sourceChild) {
            self::assertSame('en_US', $sourceChild->getLocale());
            self::assertSame($parent, $sourceChild->getParentSimple());
        }
    }

    /**
     * WP22 negative proof: a cycle-guard fallback must never mutate the source entity.
     *
     * translate($child, 'de_DE') marks (childTuuid, de_DE) in-progress for the whole
     * frame, then clones $child -> DoctrineObjectHandler::translateProperties() walks its
     * own $parentSimple property (a ManyToOne with inversedBy, reached via
     * BidirectionalManyToOneHandler in the "direct form") -> the parent gets cloned in
     * turn -> its own translateProperties() walks $simpleChildren (a OneToMany, reached
     * via BidirectionalOneToManyHandler) -> that loop reaches $child again, still
     * mid-translation -> EntityTranslator::processTranslation()'s in-progress check hands
     * $child straight back, unchanged. Before this fix, the loop's own back-reference
     * write then repointed $child's own FK at the translated parent -- mutating the
     * SOURCE entity the caller is still holding.
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testCycleGuardFallbackDoesNotMutateTheSourceChild(): void
    {
        $parent = new TranslatableOneToManyBidirectionalParent()->setLocale('en_US');

        $child   = new TranslatableManyToOneBidirectionalChild()->setLocale('en_US')->setParentSimple($parent);
        $sibling = new TranslatableManyToOneBidirectionalChild()->setLocale('en_US')->setParentSimple($parent);

        $parent->setSimpleChildren(new ArrayCollection([$child, $sibling]));

        $this->entityManager()->persist($parent);
        $this->entityManager()->persist($child);
        $this->entityManager()->persist($sibling);
        $this->entityManager()->flush();

        $childId = $child->getId();
        self::assertNotNull($childId);

        $childTranslation = $this->translator()->translate($child, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $childTranslation);

        // The source child is untouched: same parent instance, same locale.
        self::assertSame($parent, $child->getParentSimple());
        self::assertSame('en_US', $child->getLocale());

        $translatedParent = $childTranslation->getParentSimple();
        self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $translatedParent);
        self::assertSame(self::TARGET_LOCALE, $translatedParent->getLocale());

        // The in-progress child is absent from the translated parent's inverse-side
        // collection -- that collection is never persisted on its own (Doctrine writes the
        // owning ManyToOne side, i.e. the child's own FK) and Doctrine does not
        // retroactively complete an inverse collection from a FK write that went through a
        // different entity instance, so this is not data loss: a reload shows the complete
        // set (verified below).
        self::assertFalse($translatedParent->getSimpleChildren()->contains($child));

        // The child's ManyToOne to its parent carries no cascade -- the translated parent
        // needs its own persist() before flush(), same as any other new entity reached
        // through an uncascaded association.
        $this->entityManager()->persist($translatedParent);
        $this->entityManager()->persist($childTranslation);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        // FK unchanged: the source child's row still points at the source parent's id.
        $reloadedChild = $this->entityManager()->find(TranslatableManyToOneBidirectionalChild::class, $childId);
        self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $reloadedChild);
        $reloadedParent = $reloadedChild->getParentSimple();
        self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $reloadedParent);
        self::assertSame($parent->getId(), $reloadedParent->getId());
    }

    /**
     * WP22 kept-behaviour proof: an item the translator hands back unchanged because it
     * ALREADY carries the target locale (translate($x, $x->getLocale()) is an identity
     * operation -- see EntityTranslator::processTranslation()) is a genuine target-locale
     * entity, not a cycle-guard fallback. It keeps today's behaviour: added to the
     * translated collection and re-pointed at the translated parent.
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testChildAlreadyInTargetLocaleIsStillAddedAndRepointed(): void
    {
        $parent = new TranslatableOneToManyBidirectionalParent()->setLocale('en_US');

        $enChild = new TranslatableManyToOneBidirectionalChild()->setLocale('en_US')->setParentSimple($parent);
        $deChild = new TranslatableManyToOneBidirectionalChild()->setLocale(self::TARGET_LOCALE)->setParentSimple($parent);

        $parent->setSimpleChildren(new ArrayCollection([$enChild, $deChild]));

        $this->entityManager()->persist($parent);
        $this->entityManager()->persist($enChild);
        $this->entityManager()->persist($deChild);
        $this->entityManager()->flush();

        $parentTranslation = $this->translator()->translate($parent, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $parentTranslation);

        $translatedChildren = $parentTranslation->getSimpleChildren();
        self::assertCount(2, $translatedChildren, 'the en_US child gets translated, the de_DE child is kept');

        self::assertTrue($translatedChildren->contains($deChild), 'the already-de_DE child is the very same instance, still present');
        self::assertSame($parentTranslation, $deChild->getParentSimple(), 'and re-pointed at the translated parent');
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateBidirectionalManyToOne(): void
    {
        $parent = new TranslatableOneToManyBidirectionalParent()
            ->setLocale('de_DE');
        $this->entityManager()->persist($parent);

        $child = new TranslatableManyToOneBidirectionalChild()
            ->setLocale('de_DE')
            ->setParentSimple($parent);
        $this->entityManager()->persist($child);

        $childTranslation = $this->translator()->translate($child, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $childTranslation);

        $this->entityManager()->persist($childTranslation);

        $this->entityManager()->flush();

        $translatedParent = $childTranslation->getParentSimple();
        self::assertNotNull($translatedParent, 'Translated child should have a parent');
        self::assertSame(self::TARGET_LOCALE, $translatedParent->getLocale());
        self::assertEquals(
            $translatedParent->getTuuid(),
            $translatedParent->getTuuid(),
        );
    }
}
