<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyOwningChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyOwningParent;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneOwningChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneOwningParent;

/**
 * Bidirectional associations where the TRANSLATED entity is the owning side (inversedBy).
 *
 * The other bidirectional test cases all translate the inverse side, which hid the fact
 * that the handlers only ever looked for mappedBy.
 */
final class TranslatableOwningSideBidirectionalTest extends IntegrationTestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testOneToOneOwningSideRepointsChildAtTheTranslatedParent(): void
    {
        $child  = new TranslatableOneToOneOwningChild()->setLocale('en_US');
        $parent = new TranslatableOneToOneOwningParent()->setLocale('en_US');

        $parent->setOwningChild($child);
        $child->setOwningParent($parent);

        $this->entityManager()->persist($parent);
        $this->entityManager()->persist($child);

        $parentTranslation = $this->translator()->translate($parent, self::TARGET_LOCALE);

        $this->entityManager()->persist($parentTranslation);
        $this->entityManager()->flush();

        self::assertIsTranslation($parent, $parentTranslation, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableOneToOneOwningParent::class, $parentTranslation);

        $translatedChild = $parentTranslation->getOwningChild();
        self::assertNotNull($translatedChild);
        self::assertSame(self::TARGET_LOCALE, $translatedChild->getLocale());

        // The back-reference must follow the translation instead of still pointing at the
        // original, untranslated parent.
        self::assertSame($parentTranslation, $translatedChild->getOwningParent());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testManyToManyOwningSideTranslatesInsteadOfThrowing(): void
    {
        $child1 = new TranslatableManyToManyOwningChild()->setLocale('en_US');
        $child2 = new TranslatableManyToManyOwningChild()->setLocale('en_US');

        $this->entityManager()->persist($child1);
        $this->entityManager()->persist($child2);

        $parent = new TranslatableManyToManyOwningParent()->setLocale('en_US');
        $parent
            ->addOwningChild($child1)
            ->addOwningChild($child2);

        $this->entityManager()->persist($parent);

        // Before the direction fix this threw:
        // Association "...::owningChildren" is not a bidirectional ManyToMany (missing mappedBy).
        $parentTranslation = $this->translator()->translate($parent, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableManyToManyOwningParent::class, $parentTranslation);

        $this->entityManager()->persist($parentTranslation);
        $this->entityManager()->flush();

        self::assertIsTranslation($parent, $parentTranslation, self::TARGET_LOCALE);

        $translatedChildren = $parentTranslation->getOwningChildren();
        self::assertCount(2, $translatedChildren);

        foreach ($translatedChildren as $translatedChild) {
            self::assertSame(self::TARGET_LOCALE, $translatedChild->getLocale());
            self::assertTrue($translatedChild->getOwningParents()->contains($parentTranslation));
        }

        // The source parent keeps its own, untranslated children
        self::assertCount(2, $parent->getOwningChildren());
        foreach ($parent->getOwningChildren() as $sourceChild) {
            self::assertSame('en_US', $sourceChild->getLocale());
        }
    }
}
