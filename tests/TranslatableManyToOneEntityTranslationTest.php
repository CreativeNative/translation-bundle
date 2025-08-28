<?php

namespace TMI\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOne;

/**
 * @author Arthur Guigand <aguigand@tmi.fr>
 */
class TranslatableManyToOneEntityTranslationTest extends TestCase
{
    const TARGET_LOCALE = 'fr';

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateSimpleValue(): void
    {
        $associatedEntity = new Scalar()
            ->setLocale('en')
            ->setTitle('simple');

        $entity =
            new TranslatableManyToOne()
                ->setLocale('en')
                ->setSimple($associatedEntity);

        $this->entityManager->persist($entity);

        /** @var TranslatableManyToOne $translation */
        $translation = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->flush();
        $this->assertNotEquals($associatedEntity, $translation->getSimple());
//        $this->assertAttributeContains(self::TARGET_LOCALE, 'locale', $translation->getSimple());
        $this->assertIsTranslation($entity, $translation);
    }


    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItMustAssociateExistingTranslation(): void
    {
        $associatedEntity = new Scalar()
            ->setLocale('en')
            ->setTitle('simple');
        $this->entityManager->persist($associatedEntity);

        $translatedAssociatedEntity = $this->translator->translate($associatedEntity, self::TARGET_LOCALE);

        $entity =
            new TranslatableManyToOne()
                ->setLocale('en')
                ->setSimple($associatedEntity);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        /** @var TranslatableManyToOne $translation */
        $translation = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->flush();
        $this->assertNotEquals($associatedEntity, $translation->getSimple());
        $this->assertEquals($translatedAssociatedEntity, $translation->getSimple());
//        $this->assertAttributeContains(self::TARGET_LOCALE, 'locale', $translation->getSimple());
        $this->assertIsTranslation($entity, $translation);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function testItCanShareTranslatableEntityValueAmongstTranslations(): void
    {
        $associatedEntity = new Scalar()
            ->setLocale('en')
            ->setTitle('shared');

        $this->entityManager->persist($associatedEntity);
        $this->entityManager->flush();

        // Pre-set the translation to confirm that it'll
        // be picked up by the parent's translation.
        $translationAssociatedEntity = $this->translator->translate($associatedEntity, self::TARGET_LOCALE);

        $this->entityManager->persist($translationAssociatedEntity);
        $this->entityManager->flush();

        $entity =
            new TranslatableManyToOne()
                ->setLocale('en')
                ->setShared($associatedEntity);

        $this->entityManager->persist($entity);

        /** @var TranslatableManyToOne $translation */
        $translation = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->persist($translation);

        $this->entityManager->flush();
        $this->assertEquals($translationAssociatedEntity, $translation->getShared());
        $this->assertIsTranslation($entity, $translation);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanEmptyTranslatableEntityValue(): void
    {
        $associatedEntity = new Scalar()
            ->setLocale('en')
            ->setTitle('empty');

        $entity =
            new TranslatableManyToOne()
                ->setLocale('en')
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
//        $this->assertAttributeContains(self::TARGET_LOCALE, 'locale', $translation);
//        $this->assertAttributeContains($source->getTuuid(), 'tuuid', $translation);
        $this->assertNotSame(spl_object_hash($source), spl_object_hash($translation));
    }
}
