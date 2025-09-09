<?php

namespace Tmi\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneUnidirectional;

final class TranslatableOneToOneUnidirectionalTest extends TestCase
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

        $entity = new TranslatableOneToOneUnidirectional()
                ->setLocale('en')
                ->setSimple($associatedEntity);

        $this->entityManager->persist($entity);

        $translation = $this->translator->translate($entity, self::TARGET_LOCALE);
        assert($translation instanceof TranslatableOneToOneUnidirectional);

        $this->entityManager->flush();
        self::assertNotEquals($associatedEntity, $translation->getSimple());
        self::assertEquals(self::TARGET_LOCALE, $translation->getSimple()->getLocale());
        self::assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function testItCanShareTranslatableEntityValueAmongstTranslations(): void
    {
        $associatedEntity1 = new Scalar()
            ->setLocale('en')
            ->setTitle('shared');

        $associatedEntity2 = new Scalar()
            ->setLocale('en')
            ->setTitle('shared');

        $entity = new TranslatableOneToOneUnidirectional()
            ->setLocale('en')
            ->setShared($associatedEntity1);

        $this->entityManager->persist($entity);

        $translation = $this->translator->translate($entity, self::TARGET_LOCALE);
        assert($translation instanceof TranslatableOneToOneUnidirectional);
        $translation->setShared($associatedEntity2);

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        self::assertEquals('shared', $translation->getShared()->getTitle());
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

        $entity = new TranslatableOneToOneUnidirectional()
                ->setLocale('en')
                ->setEmpty($associatedEntity);

        $this->entityManager->persist($entity);

        $translation = $this->translator->translate($entity, self::TARGET_LOCALE);
        assert($translation instanceof TranslatableOneToOneUnidirectional);

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        self::assertEquals(null, $translation->getEmpty());
        self::assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
    }
}
