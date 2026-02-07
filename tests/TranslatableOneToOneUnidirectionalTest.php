<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneUnidirectional;

final class TranslatableOneToOneUnidirectionalTest extends IntegrationTestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateSimpleValue(): void
    {
        $associatedEntity = new Scalar()
            ->setLocale('en_US')
            ->setTitle('simple');

        $entity = new TranslatableOneToOneUnidirectional()
                ->setLocale('en_US')
                ->setSimple($associatedEntity);

        $this->entityManager()->persist($entity);

        $translation = $this->translator()->translate($entity, 'de_DE');
        self::assertInstanceOf(TranslatableOneToOneUnidirectional::class, $translation);

        $this->entityManager()->flush();
        $translatedSimple = $translation->getSimple();
        self::assertNotNull($translatedSimple, 'Translated entity should have a simple association');
        self::assertNotEquals($associatedEntity, $translatedSimple);
        self::assertSame('de_DE', $translatedSimple->getLocale());
        self::assertIsTranslation($entity, $translation, 'de_DE');
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function testItCanShareTranslatableEntityValueAmongstTranslations(): void
    {
        $associatedEntity1 = new Scalar()
            ->setLocale('en_US')
            ->setTitle('shared');

        $associatedEntity2 = new Scalar()
            ->setLocale('en_US')
            ->setTitle('shared');

        $entity = new TranslatableOneToOneUnidirectional()
            ->setLocale('en_US')
            ->setShared($associatedEntity1);

        $this->entityManager()->persist($entity);

        $translation = $this->translator()->translate($entity, 'de_DE');
        self::assertInstanceOf(TranslatableOneToOneUnidirectional::class, $translation);
        $translation->setShared($associatedEntity2);

        $this->entityManager()->persist($translation);
        $this->entityManager()->flush();

        $translatedShared = $translation->getShared();
        self::assertNotNull($translatedShared, 'Translated entity should have a shared association');
        self::assertSame('shared', $translatedShared->getTitle());
        self::assertIsTranslation($entity, $translation, 'de_DE');
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanEmptyTranslatableEntityValue(): void
    {
        $associatedEntity = new Scalar()
            ->setLocale('en_US')
            ->setTitle('empty');

        $entity = new TranslatableOneToOneUnidirectional()
                ->setLocale('en_US')
                ->setEmpty($associatedEntity);

        $this->entityManager()->persist($entity);

        $translation = $this->translator()->translate($entity, 'de_DE');
        self::assertInstanceOf(TranslatableOneToOneUnidirectional::class, $translation);

        $this->entityManager()->persist($translation);
        $this->entityManager()->flush();

        self::assertEquals(null, $translation->getEmpty());
        self::assertIsTranslation($entity, $translation, 'de_DE');
    }
}
