<?php

namespace TMI\TranslationBundle\Test;

use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOne;

/**
 * @author Arthur Guigand <aguigand@tmi.fr>
 */
class TranslatableManyToOneEntityTranslationTest extends TestCase
{
    const TARGET_LOCALE = 'fr';

    public function testIt_can_translate_simple_value(): void
    {
        $associatedEntity = new Scalar()->setTitle('simple');

        $entity =
            new TranslatableManyToOne()
                ->setSimple($associatedEntity);

        $this->entityManager->persist($entity);

        /** @var TranslatableManyToOne $translation */
        $translation = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->flush();
        $this->assertNotEquals($associatedEntity, $translation->getSimple());
        $this->assertAttributeContains(self::TARGET_LOCALE, 'locale', $translation->getSimple());
        $this->assertIsTranslation($entity, $translation);
    }


    public function testIt_must_associate_existing_translation(): void
    {
        $associatedEntity = new Scalar()->setTitle('simple');
        $this->entityManager->persist($associatedEntity);

        $translatedAssociatedEntity = $this->translator->translate($associatedEntity, self::TARGET_LOCALE);

        $entity =
            new TranslatableManyToOne()
                ->setSimple($associatedEntity);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        /** @var TranslatableManyToOne $translation */
        $translation = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->flush();
        $this->assertNotEquals($associatedEntity, $translation->getSimple());
        $this->assertEquals($translatedAssociatedEntity, $translation->getSimple());
        $this->assertAttributeContains(self::TARGET_LOCALE, 'locale', $translation->getSimple());
        $this->assertIsTranslation($entity, $translation);
    }

    public function testIt_can_share_translatable_entity_value_amongst_translations(): void
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
            new TranslatableManyToOne()
                ->setShared($associatedEntity);

        $this->entityManager->persist($entity);

        /** @var TranslatableManyToOne $translation */
        $translation = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->persist($translation);

        $this->entityManager->flush();
        $this->assertEquals($translationAssociatedEntity, $translation->getShared());
        $this->assertIsTranslation($entity, $translation);
    }

    public function testIt_can_empty_translatable_entity_value(): void
    {
        $associatedEntity = new Scalar()->setTitle('empty');

        $entity =
            new TranslatableManyToOne()
                ->setEmpty($associatedEntity);

        $this->entityManager->persist($entity);

        /** @var TranslatableManyToOne $translation */
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
