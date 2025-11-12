<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalParent;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\BidirectionalManyToManyHandler;

final class TranslatableManyToManyBidirectionalTest extends IntegrationTestCase
{
    private BidirectionalManyToManyHandler $handler;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();
        $this->handler = new BidirectionalManyToManyHandler(
            $this->attributeHelper,
            $this->entityManager,
            $this->translator,
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

        $this->entityManager->persist($child1);
        $this->entityManager->persist($child2);
        $this->entityManager->persist($child3);

        // Create 1 parent entity
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en_US');
        $parent
            ->addSimpleChild($child1)
            ->addSimpleChild($child2)
            ->addSimpleChild($child3);
        $this->entityManager->persist($parent);

        // Translate the parent
        $parentTranslation = $this->translator->translate($parent, self::TARGET_LOCALE);
        $this->assertInstanceOf(TranslatableManyToManyBidirectionalParent::class, $parentTranslation);
        $this->entityManager->persist($parentTranslation);
        $this->entityManager->flush();

        // Make sure the children of the translated parent are
        // translated and their parent is $translatedParent
        foreach ($parentTranslation->getSimpleChildren() as $child) {
            $this->assertInstanceOf(TranslatableManyToManyBidirectionalChild::class, $child);
            self::assertSame($parent, $child->getSimpleParents()->first());
        }

        self::assertSame(self::TARGET_LOCALE, $parentTranslation->getLocale());
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

        $this->entityManager->persist($child1);
        $this->entityManager->persist($child2);
        $this->entityManager->persist($child3);

        // Create 1 parent entity
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en_US');
        $parent
            ->addEmptyChild($child1)
            ->addEmptyChild($child2)
            ->addEmptyChild($child3);
        $this->entityManager->persist($parent);
        $this->entityManager->flush();

        // Translate the parent
        $parentTranslation = $this->translator->translate($parent, self::TARGET_LOCALE);
        $this->assertInstanceOf(TranslatableManyToManyBidirectionalParent::class, $parentTranslation);

        $this->entityManager->persist($parentTranslation);
        $this->entityManager->flush();

        // give the handler explicit property info so it can clear the correct collection
        $prop = new \ReflectionProperty(TranslatableManyToManyBidirectionalParent::class, 'emptyChildren');

        $args = new TranslationArgs($parent->getEmptyChildren(), 'en_US', self::TARGET_LOCALE)
            ->setTranslatedParent($parentTranslation)
            ->setProperty($prop);

        $clearedCollection = $this->handler->handleEmptyOnTranslate($args);

        self::assertInstanceOf(ArrayCollection::class, $clearedCollection);
        self::assertCount(0, $clearedCollection);

        // Check translated parent property is actually empty
        self::assertCount(0, $parentTranslation->getEmptyChildren());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanShareManyToMany(): void
    {
        $child = new TranslatableManyToManyBidirectionalChild();
        $this->entityManager->persist($child);

        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en_US');
        $parent->addSharedChild($child);
        $this->entityManager->persist($parent);
        $this->entityManager->flush();

        $parentTranslation = $this->translator->translate($parent, self::TARGET_LOCALE);
        $this->assertInstanceOf(TranslatableManyToManyBidirectionalParent::class, $parentTranslation);
        $this->entityManager->persist($parentTranslation);
        $this->entityManager->flush();

        self::assertCount(1, $parent->getSharedChildren());
        self::assertCount(1, $parentTranslation->getSharedChildren());

        self::assertEquals($parent->getId(), $parent->getSharedChildren()->first()->getSharedParents()->first()->getId());
    }
}
