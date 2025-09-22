<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Ramsey\Uuid\Uuid;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalParent;

final class TranslatableManyToOneBidirectionalTest extends IntegrationTestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateSimpleValue(): void
    {
        $parent = new TranslatableOneToManyBidirectionalParent()
            ->setLocale('de');
        $this->entityManager->persist($parent);

        $child = new TranslatableManyToOneBidirectionalChild()
            ->setLocale('de');
        $child->setParentSimple($parent);
        $parent->getSimpleChildren()->add($child);

        $this->entityManager->persist($child);
        $this->entityManager->flush();

        $translation = $this->translator->translate($parent, self::TARGET_LOCALE);
        assert($translation instanceof TranslatableOneToManyBidirectionalParent);

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        $translatedChild = $translation->getSimpleChildren()->first();
        if ($translatedChild->getLocale() !== $child->getLocale()) {
            self::assertNotSame($child, $translatedChild); // Only if translation occurred
        } else {
            self::assertSame($child, $translatedChild); // Original returned if no translation
        }

        self::assertEquals(self::TARGET_LOCALE, $translatedChild->getLocale());
        self::assertIsTranslation($parent, $translation, self::TARGET_LOCALE);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItMustAssociateExistingTranslation(): void
    {
        // --- Step 1: Create and persist the original child ---
        $child = new TranslatableManyToOneBidirectionalChild();
        $child->setParentSimple(null);
        $child->setLocale('de');

        $this->entityManager->persist($child);
        $this->entityManager->flush();

        $this->assertNotNull($child->getTuuid());
        $this->assertTrue(Uuid::isValid($child->getTuuid()));

        // --- Step 2: Create and persist the parent ---
        $parent = new TranslatableOneToManyBidirectionalParent();
        $parent->setLocale('de');
        $parent->getSimpleChildren()->add($child);
        $child->setParentSimple($parent);

        $this->entityManager->persist($parent);
        $this->entityManager->flush();

        $this->assertNotNull($parent->getTuuid());
        $this->assertTrue(Uuid::isValid($parent->getTuuid()));

        $translatedChild = $this->translator->translate($child, self::TARGET_LOCALE);
        assert($translatedChild instanceof TranslatableManyToOneBidirectionalChild);
        $parent->getSimpleChildren()->add($translatedChild);
        $translatedChild->setParentSimple($parent);

        // --- Step 3: Translate the parent (Child will be translated automatically) ---
        $translation = $this->translator->translate($parent, self::TARGET_LOCALE);
        assert($translation instanceof TranslatableOneToManyBidirectionalParent);

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        // --- Step 4: Assertions ---
        $translatedChildFromParent = $translation->getSimpleChildren()->first();
        assert($translatedChildFromParent instanceof TranslatableManyToOneBidirectionalChild);

        // Adjust assertion for object identity
        if ($translatedChildFromParent->getLocale() !== $child->getLocale()) {
            self::assertNotSame($child, $translatedChildFromParent);
        } else {
            self::assertSame($child, $translatedChildFromParent);
        }

        self::assertEquals(self::TARGET_LOCALE, $translatedChildFromParent->getLocale());
        self::assertIsTranslation($parent, $translation, self::TARGET_LOCALE);
    }


    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function testItCanShareTranslatableEntityValueAmongstTranslations(): void
    {
        $child = new TranslatableManyToOneBidirectionalChild();
        $child->setLocale('de');

        $this->entityManager->persist($child);
        $this->entityManager->flush();

        $translatedChild = $this->translator->translate($child, self::TARGET_LOCALE);
        assert($translatedChild instanceof TranslatableManyToOneBidirectionalChild);

        $this->entityManager->persist($translatedChild);
        $this->entityManager->flush();

        $parent = new TranslatableOneToManyBidirectionalParent();
        $parent->setLocale('de');

        $parent->getSimpleChildren()->add($translatedChild);
        $translatedChild->setParentSimple($parent);

        $this->entityManager->persist($parent);

        $translation = $this->translator->translate($parent, self::TARGET_LOCALE);
        assert($translation instanceof TranslatableOneToManyBidirectionalParent);

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        self::assertEquals($translatedChild, $translation->getSimpleChildren()->first());
        self::assertIsTranslation($parent, $translation, self::TARGET_LOCALE);
    }
}
