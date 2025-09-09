<?php

namespace Tmi\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOne;

final class TranslatableManyToOneEntityBidirectionalTest extends TestCase
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
        self::assertNotEquals($associatedEntity, $translation->getSimple());
        self::assertEquals(self::TARGET_LOCALE, $translation->getSimple()->getLocale());
        self::assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
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

        self::assertNotEquals($associatedEntity, $translation->getSimple());

        self::assertEquals($translatedAssociatedEntity, $translation->getSimple());
        self::assertEquals(self::TARGET_LOCALE, $translation->getSimple()->getLocale());
        self::assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
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
        self::assertEquals($translationAssociatedEntity, $translation->getShared());
        self::assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
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

        self::assertEquals(null, $translation->getEmpty());
        self::assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
    }
}
