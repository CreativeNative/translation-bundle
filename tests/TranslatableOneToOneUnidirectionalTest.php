<?php

namespace TMI\TranslationBundle\Test;

use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneUnidirectional;

/**
 * @author Arthur Guigand <aguigand@tmi.fr>
 */
class TranslatableOneToOneUnidirectionalTest extends TestCase
{
    const TARGET_LOCALE = 'fr';

    public function testItCanTranslateSimpleValue(): void
    {
        $associatedEntity = new Scalar()->setTitle('simple');

        $entity =
            new TranslatableOneToOneUnidirectional()
                ->setSimple($associatedEntity);

        $this->entityManager->persist($entity);

        /** @var TranslatableOneToOneUnidirectional $translation */
        $translation = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->flush();
        $this->assertNotEquals($associatedEntity, $translation->getSimple());
        $this->assertAttributeContains(self::TARGET_LOCALE, 'locale', $translation->getSimple());
        $this->assertIsTranslation($entity, $translation);
    }

    public function testItCanShareTranslatableEntityValueAmongstTranslations(): void
    {
        $associatedEntity = new Scalar()->setTitle('shared');
        $this->entityManager->persist($associatedEntity);
        $this->entityManager->flush();

        // Pre-set the translation to confirm that it'll
        // be picked up by the parent's translation.
        $translationAssociatedEntity = $this->translator->translate($associatedEntity, self::TARGET_LOCALE);

        $this->entityManager->persist($translationAssociatedEntity);
        $this->entityManager->flush();

        $entity =
            new TranslatableOneToOneUnidirectional()
                ->setShared($associatedEntity);

        $this->entityManager->persist($entity);

        /** @var TranslatableOneToOneUnidirectional $translation */
        $translation = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        $this->assertEquals($translationAssociatedEntity, $translation->getShared());
        $this->assertIsTranslation($entity, $translation);
    }

    public function testItCanEmptyTranslatableEntityValue(): void
    {
        $associatedEntity = new Scalar()->setTitle('empty');

        $entity =
            new TranslatableOneToOneUnidirectional()
                ->setEmpty($associatedEntity);

        $this->entityManager->persist($entity);

        /** @var TranslatableOneToOneUnidirectional $translation */
        $translation = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        $this->assertEquals(null, $translation->getEmpty());
        $this->assertIsTranslation($entity, $translation);
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
