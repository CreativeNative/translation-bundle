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
     * A unidirectional OneToOne (no inversedBy) falls through the same way a
     * unidirectional ManyToOne does -- neither BidirectionalOneToOneHandler nor any
     * other dedicated association handler supports it, so it reaches
     * TranslatableEntityHandler as the catch-all, which now rejects a shared
     * association to a translatable target instead of silently translating it.
     */
    public function testItCannotShareTranslatableEntityValueAmongstTranslations(): void
    {
        $associatedEntity = new Scalar()
            ->setLocale('en_US')
            ->setTitle('shared');

        $entity = new TranslatableOneToOneUnidirectional()
            ->setLocale('en_US')
            ->setShared($associatedEntity);

        $this->entityManager()->persist($entity);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessageMatches('/shared/');

        $this->translator()->translate($entity, 'de_DE');
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
