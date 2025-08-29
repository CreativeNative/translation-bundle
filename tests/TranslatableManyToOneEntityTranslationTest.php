<?php

namespace TMI\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOne;

class TranslatableManyToOneEntityTranslationTest extends TestCase
{
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
        $this->assertEquals(self::TARGET_LOCALE, $translation->getSimple()->getLocale());
        $this->assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItMustAssociateExistingTranslation(): void
    {
        $associatedEntity = new Scalar()
            ->setLocale('fr')
            ->setTitle('simple');
        $this->entityManager->persist($associatedEntity);

        $translatedAssociatedEntity = $this->translator->translate($associatedEntity, self::TARGET_LOCALE);

        $entity =
            new TranslatableManyToOne()
                ->setLocale('fr')
                ->setSimple($associatedEntity);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        /** @var TranslatableManyToOne $translation */
        $translation = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->flush();

        $this->assertNotEquals($associatedEntity, $translation->getSimple());

        $this->assertEquals($translatedAssociatedEntity, $translation->getSimple());
        $this->assertEquals(self::TARGET_LOCALE, $translation->getSimple()->getLocale());
        $this->assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
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
        $this->assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
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
        $this->assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
    }
}
