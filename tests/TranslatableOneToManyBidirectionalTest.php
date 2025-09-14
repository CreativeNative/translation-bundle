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
            new TranslatableManyToOneBidirectionalChild()->setLocale('de'),
            new TranslatableManyToOneBidirectionalChild()->setLocale('de'),
            new TranslatableManyToOneBidirectionalChild()->setLocale('de'),
        ]);

        $parent = new TranslatableOneToManyBidirectionalParent()
            ->setLocale('de');

        // Persist children first and assign parent
        foreach ($children as $child) {
            $child->setParentSimple($parent);
            $this->entityManager->persist($child);
        }

        $parent->setSimpleChildren($children);
        $this->entityManager->persist($parent);
        $this->entityManager->flush();

        $parentTranslation = $this->translator->translate($parent, self::TARGET_LOCALE);
        assert($parentTranslation instanceof TranslatableOneToManyBidirectionalParent);

        $this->entityManager->persist($parentTranslation);
        $this->entityManager->flush();

        self::assertEquals(self::TARGET_LOCALE, $parentTranslation->getSimpleChildren()->first()->getLocale());
        self::assertEquals(
            $parent->getSimpleChildren()->first()->getTuuid(),
            $parentTranslation->getSimpleChildren()->first()->getTuuid()
        );
    }


    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateBidirectionalManyToOne(): void
    {
        $parent = new TranslatableOneToManyBidirectionalParent()
            ->setLocale('de');
        $this->entityManager->persist($parent);

        $child = new TranslatableManyToOneBidirectionalChild()
            ->setLocale('de')
            ->setParentSimple($parent);
        $this->entityManager->persist($child);

        $childTranslation = $this->translator->translate($child, self::TARGET_LOCALE);
        assert($childTranslation instanceof TranslatableManyToOneBidirectionalChild);

        $this->entityManager->persist($childTranslation);

        $this->entityManager->flush();

        self::assertEquals(self::TARGET_LOCALE, $childTranslation->getParentSimple()->getLocale());
        self::assertEquals(
            $childTranslation->getParentSimple()->getTuuid(),
            $childTranslation->getParentSimple()->getTuuid()
        );
    }
}
