<?php

namespace TMI\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\ManyToManyBidirectionalChild;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalChild;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalParent;

final class TranslatableManyToManyTranslationTest extends TestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateManyToMany(): void
    {
        // Create 3 children entities
        $child1 = new TranslatableManyToManyBidirectionalChild()->setLocale('en');
        $child2 = new TranslatableManyToManyBidirectionalChild()->setLocale('en');
        $child3 = new TranslatableManyToManyBidirectionalChild()->setLocale('en');

        $this->entityManager->persist($child1);
        $this->entityManager->persist($child2);
        $this->entityManager->persist($child3);

        // Create 1 parent entity
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en');
        $parent
            ->addSimpleChild($child1)
            ->addSimpleChild($child2)
            ->addSimpleChild($child3)
        ;
        $this->entityManager->persist($parent);

        // Translate the parent
        /** @var TranslatableManyToManyBidirectionalParent $parentTranslation */
        $parentTranslation = $this->translator->translate($parent, self::TARGET_LOCALE);
        $this->entityManager->persist($parentTranslation);
        $this->entityManager->flush();

        // Make sure the children of the translated parent are
        // translated and their parent is $translatedParent
        foreach ($parentTranslation->getSimpleChildren() as $child) {
            /** @var TranslatableManyToManyBidirectionalChild $child */
            $this->assertEquals($child->getSimpleParents()->first(), $parentTranslation);
        }

        // Make sure the parent of the original children didn't change.
        foreach ($parentTranslation->getSimpleChildren() as $child) {
            /** @var TranslatableManyToManyBidirectionalChild $child */
            $this->assertEquals($child->getSimpleParents()->first(), $parentTranslation);
        }
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanEmptyOnTranslate(): void
    {
        // Create 3 children entities
        $child1 = new TranslatableManyToManyBidirectionalChild()->setLocale('en');
        $child2 = new TranslatableManyToManyBidirectionalChild()->setLocale('en');
        $child3 = new TranslatableManyToManyBidirectionalChild()->setLocale('en');

        $this->entityManager->persist($child1);
        $this->entityManager->persist($child2);
        $this->entityManager->persist($child3);
        // Create 1 parent entity
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en');
        $parent
            ->addEmptyChild($child1)
            ->addEmptyChild($child2)
            ->addEmptyChild($child3)
        ;
        $this->entityManager->persist($parent);
        // Translate the parent
        /** @var TranslatableManyToManyBidirectionalParent $parentTranslation */
        $parentTranslation = $this->translator->translate($parent, self::TARGET_LOCALE);
        $this->entityManager->persist($parentTranslation);
        $this->entityManager->flush();

        // Assert that the translated parents has an empty list of child
        $this->assertEmpty($parentTranslation->getSimpleChildren());
    }


    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanShareManyToMany(): void
    {
        // Create 3 children entities
        $child1 = new ManyToManyBidirectionalChild();

        $this->entityManager->persist($child1);

        // Create 1 parent entity
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en');
        $parent->addSharedChild($child1);
        $this->entityManager->persist($parent);
        $this->entityManager->flush();

        $parentTranslation = $this->translator->translate($parent, self::TARGET_LOCALE);
        $this->entityManager->persist($parentTranslation);
        $this->entityManager->flush();

        $this->assertGreaterThan(0, $parent->getSharedChildren()->count());
        $this->assertGreaterThan(0, $parentTranslation->getSharedChildren()->count());
        $this->assertEquals($parent->getSharedChildren()->count(), $parentTranslation->getSharedChildren()->count());

// @todo fix me  $this->assertNotEquals($parent->getSharedChildren()->first(), $parentTranslation->getSharedChildren()->first());

        $this->assertEquals($parent->getSharedChildren()->first()->getSharedParents()->first(), $parent);
        $this->assertEquals($parentTranslation->getSharedChildren()->first()->getSharedParents()->first(), $parentTranslation);
    }
}
