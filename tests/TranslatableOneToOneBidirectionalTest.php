<?php

namespace Tmi\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalParent;

final class TranslatableOneToOneBidirectionalTest extends TestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateSimpleValue(): void
    {
        $child  = new TranslatableOneToOneBidirectionalChild()->setLocale('en');
        $parent = new TranslatableOneToOneBidirectionalParent()->setLocale('en');

        $parent->setSimpleChild($child);
        $child->setSimpleParent($parent);

        $this->entityManager->persist($parent);

        $parentTranslation = $this->translator->translate($parent, self::TARGET_LOCALE);

        $this->entityManager->persist($parentTranslation);
        $this->entityManager->flush();

        self::assertIsTranslation($parent, $parentTranslation, self::TARGET_LOCALE);
        self::assertEquals(self::TARGET_LOCALE, $parentTranslation->getSimpleChild()->getLocale());
    }

    public function testItCannotShareTranslatableEntityValueAmongstTranslations(): void
    {
        $this->expectException(\ErrorException::class);

        $child  = new TranslatableOneToOneBidirectionalChild()->setLocale('en');
        $parent = new TranslatableOneToOneBidirectionalParent()->setLocale('en');

        $parent->setSharedChild($child);
        $child->setSharedParent($parent);

        $this->translator->translate($parent, self::TARGET_LOCALE);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanEmptyTranslatableEntityValue(): void
    {
        $child  = new TranslatableOneToOneBidirectionalChild()->setLocale('en');
        $parent = new TranslatableOneToOneBidirectionalParent()->setLocale('en');

        $parent->setEmptyChild($child);
        $child->setEmptyParent($parent);

        $this->entityManager->persist($parent);
        $this->entityManager->persist($child);

        $parentTranslation = $this->translator->translate($parent, self::TARGET_LOCALE);

        $this->entityManager->persist($parentTranslation);
        $this->entityManager->flush();

        self::assertIsTranslation($parent, $parentTranslation, self::TARGET_LOCALE);

        self::assertEquals(null, $parentTranslation->getEmptyChild());
    }
}
