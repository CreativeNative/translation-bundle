<?php

namespace Tmi\TranslationBundle\Test;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalParent;

final class TranslatableOneToManyBidirectionalTest extends TestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateBidirectionalOneToMany(): void
    {
        $children = new ArrayCollection([
            new TranslatableOneToManyBidirectionalChild()->setLocale('en'),
            new TranslatableOneToManyBidirectionalChild()->setLocale('en'),
            new TranslatableOneToManyBidirectionalChild()->setLocale('en'),
        ]);

        $parent = new TranslatableOneToManyBidirectionalParent()
            ->setLocale('en')
            ->setChildren($children);
        $this->entityManager->persist($parent);
        /** @var TranslatableOneToManyBidirectionalParent $parentTranslation */
        $parentTranslation = $this->translator->translate($parent, self::TARGET_LOCALE);
        $this->entityManager->persist($parentTranslation);

        $this->entityManager->flush();

        self::assertEquals(self::TARGET_LOCALE, $parentTranslation->getChildren()->first()->getLocale());
        self::assertEquals(
            $parent->getChildren()->first()->getTuuid(),
            $parentTranslation->getChildren()->first()->getTuuid()
        );
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateBidirectionalManyToOne(): void
    {
        $parent = new TranslatableOneToManyBidirectionalParent()->setLocale('en');
        $child  = new TranslatableOneToManyBidirectionalChild()->setLocale('en');

        $child->setParent($parent);
        $this->entityManager->persist($child);

        $childTranslation = $this->translator->translate($child, self::TARGET_LOCALE);
        $this->entityManager->persist($childTranslation);

        $this->entityManager->flush();

        self::assertEquals(self::TARGET_LOCALE, $childTranslation->getParent()->getLocale());
        self::assertEquals(
            $childTranslation->getParent()->getTuuid(),
            $childTranslation->getParent()->getTuuid()
        );
    }
}
