<?php

namespace TMI\TranslationBundle\Test;

use Doctrine\Common\Collections\ArrayCollection;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalChild;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalParent;

/**
 * @author Arthur Guigand <aguigand@tmi.fr>
 */
class TranslatableOneToManyBidirectionalTest extends AbstractBaseTest
{
    const TARGET_LOCALE = 'fr';

    public function testIt_can_translate_bidirectional_one_to_many(): void
    {
        $children = new ArrayCollection([
            new TranslatableOneToManyBidirectionalChild(),
            new TranslatableOneToManyBidirectionalChild(),
            new TranslatableOneToManyBidirectionalChild(),
        ]);

        $parent = new TranslatableOneToManyBidirectionalParent()->setChildren($children);
        $this->entityManager->persist($parent);
        /** @var TranslatableOneToManyBidirectionalParent $parentTranslation */
        $parentTranslation = $this->translator->translate($parent, self::TARGET_LOCALE);
        $this->entityManager->persist($parentTranslation);

        $this->entityManager->flush();

        $this->assertEquals(self::TARGET_LOCALE, $parentTranslation->getChildren()->first()->getLocale());
        $this->assertEquals(
            $parent->getChildren()->first()->getTuuid(),
            $parentTranslation->getChildren()->first()->getTuuid()
        );
    }

    public function testIt_can_translate_bidirectional_many_to_one(): void
    {
        $parent = new TranslatableOneToManyBidirectionalParent();
        $child  = new TranslatableOneToManyBidirectionalChild();

        $child->setParent($parent);
        $this->entityManager->persist($child);

        $childTranslation = $this->translator->translate($child, self::TARGET_LOCALE);
        $this->entityManager->persist($childTranslation);

        $this->entityManager->flush();

        $this->assertEquals(self::TARGET_LOCALE, $childTranslation->getParent()->getLocale());
        $this->assertEquals(
            $childTranslation->getParent()->getTuuid(),
            $childTranslation->getParent()->getTuuid()
        );
    }
}
