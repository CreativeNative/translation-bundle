<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalParent;
use Tmi\TranslationBundle\Translation\Context\PropertyTranslationContext;
use Tmi\TranslationBundle\Translation\Handlers\BidirectionalManyToManyHandler;

final class TranslatableManyToManyBidirectionalTest extends IntegrationTestCase
{
    private BidirectionalManyToManyHandler $handler;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();
        $this->handler = new BidirectionalManyToManyHandler(
            $this->attributeHelper(),
            $this->entityManager(),
            $this->translator(),
        );
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateManyToMany(): void
    {
        // Create 3 children entities
        $child1 = new TranslatableManyToManyBidirectionalChild()->setLocale('en_US');
        $child2 = new TranslatableManyToManyBidirectionalChild()->setLocale('en_US');
        $child3 = new TranslatableManyToManyBidirectionalChild()->setLocale('en_US');

        $this->entityManager()->persist($child1);
        $this->entityManager()->persist($child2);
        $this->entityManager()->persist($child3);

        // Create 1 parent entity
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en_US');
        $parent
            ->addSimpleChild($child1)
            ->addSimpleChild($child2)
            ->addSimpleChild($child3);
        $this->entityManager()->persist($parent);

        // Translate the parent
        $parentTranslation = $this->translator()->translate($parent, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableManyToManyBidirectionalParent::class, $parentTranslation);
        $this->entityManager()->persist($parentTranslation);
        $this->entityManager()->flush();

        // The translated parent gets its own collection of translated children, each of them
        // pointing back at the TRANSLATED parent rather than the source one.
        $simpleChildren = $parentTranslation->getSimpleChildren();
        self::assertNotSame($parent->getSimpleChildren(), $simpleChildren);
        self::assertCount(3, $simpleChildren);

        foreach ($simpleChildren as $child) {
            self::assertSame(TranslatableManyToManyBidirectionalChild::class, $child::class);
            self::assertSame(self::TARGET_LOCALE, $child->getLocale());
            self::assertSame($parentTranslation, $child->getSimpleParents()->first());
        }

        // ...and the source graph is untouched
        self::assertCount(3, $parent->getSimpleChildren());
        foreach ($parent->getSimpleChildren() as $child) {
            self::assertSame('en_US', $child->getLocale());
        }

        self::assertSame(self::TARGET_LOCALE, $parentTranslation->getLocale());
    }

    /**
     * WP22 negative proof: a cycle-guard fallback must never mutate the source entity.
     *
     * BidirectionalManyToManyHandler::translateCollection() already detaches $item's own
     * back-reference before recursing into it (see that method's docblock), which defeats
     * a genuine in-progress cycle through this exact two-class, one-association fixture --
     * verified empirically while building this test: translating either side, alone or
     * with a second parent sharing the child, always produced a fresh clone, never the
     * in-progress fallback. Reaching the fallback for real needs $child's own tuuid to
     * already be marked in-progress through some OTHER path in the graph by the time this
     * loop's translate() call reaches it -- exactly what a real multi-branch object graph
     * (a third association, or a diamond through a third entity) would produce. Staging
     * the marker directly on the real TranslationCacheInterface reaches the very same
     * EntityTranslator::processTranslation() in-progress check such a graph would trigger,
     * without inflating this fixture into one.
     *
     * Child is the OWNING side of the 'parent_child' join table (inversedBy on its own
     * $simpleParents, see the fixture), so the bug this guards against is a persisted join
     * row linking a de_DE parent to the untranslated en_US child.
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testCycleGuardFallbackDoesNotMutateTheSourceItem(): void
    {
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en_US');
        $child  = new TranslatableManyToManyBidirectionalChild()->setLocale('en_US');
        $parent->addSimpleChild($child);

        $this->entityManager()->persist($parent);
        $this->entityManager()->persist($child);
        $this->entityManager()->flush();

        $childTuuid = $child->getTuuid()->getValue();
        $this->translationCache()->markInProgress($childTuuid, self::TARGET_LOCALE);

        try {
            $parentTranslation = $this->translator()->translate($parent, self::TARGET_LOCALE);
        } finally {
            $this->translationCache()->unmarkInProgress($childTuuid, self::TARGET_LOCALE);
        }

        self::assertInstanceOf(TranslatableManyToManyBidirectionalParent::class, $parentTranslation);

        // The source item's own back-reference collection is untouched: old code added the
        // translated (DE) parent to it, on top of the source's own (EN) parent.
        self::assertCount(1, $child->getSimpleParents());
        self::assertSame($parent, $child->getSimpleParents()->first());
        self::assertSame('en_US', $child->getLocale());

        // The in-progress child is absent from the translated parent's collection -- that
        // collection is never persisted on its own (Doctrine writes the join table from
        // the owning side, the child's own field) and Doctrine does not retroactively
        // complete an inverse collection either, so this is not a data loss: a reload of
        // either side shows the complete, correct set.
        self::assertCount(0, $parentTranslation->getSimpleChildren());

        $this->entityManager()->persist($parentTranslation);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        // Reload both sides: no row in the owning side's join table links the de_DE parent
        // to the en_US child.
        $reloadedParent = $this->entityManager()->find(
            TranslatableManyToManyBidirectionalParent::class,
            $parentTranslation->getId(),
        );
        self::assertInstanceOf(TranslatableManyToManyBidirectionalParent::class, $reloadedParent);
        self::assertCount(0, $reloadedParent->getSimpleChildren());

        $reloadedChild = $this->entityManager()->find(
            TranslatableManyToManyBidirectionalChild::class,
            $child->getId(),
        );
        self::assertInstanceOf(TranslatableManyToManyBidirectionalChild::class, $reloadedChild);
        self::assertCount(1, $reloadedChild->getSimpleParents());
        $reloadedParentSide = $reloadedChild->getSimpleParents()->first();
        self::assertInstanceOf(TranslatableManyToManyBidirectionalParent::class, $reloadedParentSide);
        self::assertSame('en_US', $reloadedParentSide->getLocale());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanEmptyOnTranslate(): void
    {
        // Create 3 children entities
        $child1 = new TranslatableManyToManyBidirectionalChild()->setLocale('en_US');
        $child2 = new TranslatableManyToManyBidirectionalChild()->setLocale('en_US');
        $child3 = new TranslatableManyToManyBidirectionalChild()->setLocale('en_US');

        $this->entityManager()->persist($child1);
        $this->entityManager()->persist($child2);
        $this->entityManager()->persist($child3);

        // Create 1 parent entity
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en_US');
        $parent
            ->addEmptyChild($child1)
            ->addEmptyChild($child2)
            ->addEmptyChild($child3);
        $this->entityManager()->persist($parent);
        $this->entityManager()->flush();

        // Translate the parent
        $parentTranslation = $this->translator()->translate($parent, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableManyToManyBidirectionalParent::class, $parentTranslation);

        $this->entityManager()->persist($parentTranslation);
        $this->entityManager()->flush();

        // give the handler explicit property info so it can clear the correct collection
        $prop = new \ReflectionProperty(TranslatableManyToManyBidirectionalParent::class, 'emptyChildren');

        $context = new PropertyTranslationContext($parent->getEmptyChildren(), 'en_US', self::TARGET_LOCALE)
            ->setTranslatedParent($parentTranslation)
            ->setProperty($prop)
            ->setEmpty(true);

        $clearedCollection = $this->handler->translate($context);

        self::assertInstanceOf(ArrayCollection::class, $clearedCollection);
        self::assertCount(0, $clearedCollection);

        // Check translated parent property is actually empty
        self::assertCount(0, $parentTranslation->getEmptyChildren());
    }

    /**
     * #[SharedAmongstTranslations] is not a valid combination with a bidirectional
     * ManyToMany: sharing one collection between locale variants would make both sides of
     * the join table ambiguous. The handler rejects it explicitly.
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItRejectsSharedAmongstTranslationsOnManyToMany(): void
    {
        $child = new TranslatableManyToManyBidirectionalChild();
        $this->entityManager()->persist($child);

        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en_US');
        $parent->addSharedChild($child);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage(
            'SharedAmongstTranslations is not allowed on bidirectional ManyToMany associations',
        );

        $this->translator()->translate($parent, self::TARGET_LOCALE);
    }
}
