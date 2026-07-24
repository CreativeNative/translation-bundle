<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneOwningChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneOwningParent;

/**
 * Bidirectional associations where the TRANSLATED entity is the owning side (inversedBy).
 *
 * The other bidirectional test cases all translate the inverse side, which hid the fact
 * that the handlers only ever looked for mappedBy.
 *
 * The ManyToMany counterpart lives in the handler unit test: BidirectionalManyToManyHandler
 * never sees a collection through the pipeline (its supports() requires the data itself to
 * be a TranslatableInterface), so the direction bug is only observable by calling it
 * directly.
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
}
