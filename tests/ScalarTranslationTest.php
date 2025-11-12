<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Tmi\TranslationBundle\Fixtures\Entity\CanNotBeNull;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;

final class ScalarTranslationTest extends IntegrationTestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateScalarValue(): void
    {
        $entity = $this->createEntity();
        $this->entityManager->flush();

        $translation = $this->translator->translate($entity, 'de_DE');
        $this->assertInstanceOf(Scalar::class, $translation);

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        self::assertIsTranslation($entity, $translation, 'de_DE');

        self::assertTrue(property_exists($translation, 'title'));
        self::assertSame('English title', $translation->getTitle());
        self::assertSame('Shared english attribute', $translation->getShared());
        self::assertNotSame('Empty english attribute', $translation->getEmpty());
        self::assertNull($translation->getEmpty());
    }

    /**
     * @todo fixme: This test is broken because of TranslatableEventSubscriber->alreadySyncedEntities. I don't know yet how to fix it.
     *
     * @throws ORMException
     */
    public function testItCanShareScalarValueAmongstTranslations(): void
    {
        $entity = $this->createEntity();

        $translation = $this->translator->translate($entity, 'de_DE');
        $this->assertInstanceOf(Scalar::class, $translation);

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        $translation->setShared('Updated shared');
        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        self::assertTrue(property_exists($entity, 'shared'));
        //        self::assertEquals('Updated shared', $entity->getShared());
        self::assertIsTranslation($entity, $translation, 'de_DE');
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanEmptyScalarValueOnTranslate(): void
    {
        $entity      = $this->createEntity();
        $translation = $this->translator->translate($entity, 'de_DE');
        $this->assertInstanceOf(Scalar::class, $translation);

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        self::assertObjectHasProperty('empty', $translation);
        self::assertEmpty($translation->getEmpty());
        self::assertIsTranslation($entity, $translation, 'de_DE');
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanNotEmptyNotNullableScalarValueOnTranslate(): void
    {
        $this->expectException(\LogicException::class);

        $entity = new CanNotBeNull()
            ->setLocale('en_US')
            ->setEmptyNotNullable('Empty not nullable attribute');

        $this->entityManager->persist($entity);

        $translation = $this->translator->translate($entity, 'de_DE');
        $this->assertInstanceOf(CanNotBeNull::class, $translation);

        $this->entityManager->flush();

        self::assertTrue(property_exists($translation, 'empty_not_nullable'));
        self::assertNotNull($translation->getEmptyNotNullable());
        self::assertNotEmpty($translation->getEmptyNotNullable());
        self::assertIsTranslation($entity, $translation, 'de_DE');
    }

    /**
     * Creates test entity.
     *
     * @throws ORMException
     */
    private function createEntity(): Scalar
    {
        $entity = new Scalar()
            ->setLocale('en_US')
            ->setTitle('English title')
            ->setShared('Shared english attribute')
            ->setEmpty('Empty english attribute');

        $this->entityManager->persist($entity);

        return $entity;
    }
}
