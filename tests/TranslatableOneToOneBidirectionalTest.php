<?php

namespace TMI\TranslationBundle\Test;

use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalChild;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalParent;

/**
 * @author Arthur Guigand <aguigand@tmi.fr>
 */
class TranslatableOneToOneBidirectionalTest extends TestCase
{
    const TARGET_LOCALE = 'fr';

    public function testItCanTranslateSimpleValue(): void
    {
        $child  = new TranslatableOneToOneBidirectionalChild();
        $parent = new TranslatableOneToOneBidirectionalParent();

        $parent->setSimpleChild($child);
        $child->setSimpleParent($parent);

        $this->entityManager->persist($parent);

        $parentTranslation = $this->translator->translate($parent, self::TARGET_LOCALE);

        $this->entityManager->persist($parentTranslation);
        $this->entityManager->flush();

        $this->assertIsTranslation($parent, $parentTranslation);
        $this->assertAttributeContains(self::TARGET_LOCALE, 'locale', $parentTranslation->getSimpleChild());
    }

    public function testItCannotShareTranslatableEntityValueAmongstTranslations(): void
    {
        $this->expectException(\ErrorException::class);

        $child  = new TranslatableOneToOneBidirectionalChild();
        $parent = new TranslatableOneToOneBidirectionalParent();

        $parent->setSharedChild($child);
        $child->setSharedParent($parent);

        $this->translator->translate($parent, self::TARGET_LOCALE);
    }

    public function testItCanEmptyTranslatableEntityValue(): void
    {
        $child  = new TranslatableOneToOneBidirectionalChild();
        $parent = new TranslatableOneToOneBidirectionalParent();

        $parent->setEmptyChild($child);
        $child->setEmptyParent($parent);

        $this->entityManager->persist($parent);
        $this->entityManager->persist($child);

        $parentTranslation = $this->translator->translate($parent, self::TARGET_LOCALE);

        $this->entityManager->persist($parentTranslation);
        $this->entityManager->flush();

        $this->assertIsTranslation($parent, $parentTranslation);

        $this->assertEquals(null, $parentTranslation->getEmptyChild());
    }

    /**
     * Assert a translation is actually a translation.
     *
     * @param TranslatableInterface $source
     * @param TranslatableInterface $translation
     */
    protected function assertIsTranslation(TranslatableInterface $source, TranslatableInterface $translation)
    {
        $this->assertAttributeContains(self::TARGET_LOCALE, 'locale', $translation);
        $this->assertAttributeContains($source->getTuuid(), 'tuuid', $translation);
        $this->assertNotSame(spl_object_hash($source), spl_object_hash($translation));
    }
}
