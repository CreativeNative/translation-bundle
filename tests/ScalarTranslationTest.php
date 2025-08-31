<?php

namespace TMI\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use LogicException;
use TMI\TranslationBundle\Fixtures\Entity\CanNotBeNull;
use TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar;

/**
 * Test for scalar value.
 */
final class ScalarTranslationTest extends TestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateScalarValue(): void
    {
        $entity = $this->createEntity();
        $translation = $this->translator->translate($entity, 'en');

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        self::assertTrue(property_exists($translation, 'title'), 'Object does not have the expected property "title".');
        self::assertEquals('Test title', $translation->getTitle());
        self::assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
    }


    /**
     * @todo fixme: This test is broken because of TranslatableEventSubscriber->alreadySyncedEntities. I don't know yet how to fix it.
     *
     * @throws ORMException
     */
    public function testItCanShareScalarValueAmongstTranslations()
    {
        $entity = $this->createEntity();
        /** @var Scalar $translation */
        $translation = $this->translator->translate($entity, 'en');
        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        // Update shared attribute
        $translation->setShared('Updated shared');
        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        self::assertTrue(property_exists($entity, 'shared'));

//      self::assertEquals('Updated shared', $entity->getShared());

        self::assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanEmptyScalarValueOnTranslate(): void
    {
        $entity = $this->createEntity();
        $translation = $this->translator->translate($entity, 'en');

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        self::assertObjectHasProperty('empty', $translation);
        self::assertEmpty($translation->getEmpty());
        self::assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanNotEmptyNotNullableScalarValueOnTranslate(): void
    {
        $this->expectException(LogicException::class);

        $entity = new CanNotBeNull()
            ->setLocale('en')
            ->setEmptyNotNullable('Empty not nullable attribute');

        $this->entityManager->persist($entity);
        $translation = $this->translator->translate($entity, 'en');

        $this->entityManager->flush();

        self::assertTrue(
            property_exists($translation, 'empty_not_nullable'),
            'Property "empty_not_nullable" not found in translation object'
        );
        self::assertNotNull($translation->getEmptyNotNullable());
        self::assertNotEmpty($translation->getEmptyNotNullable());
        self::assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
    }

    /**
     * Creates test entity.
     * @throws ORMException
     */
    private function createEntity(): Scalar
    {
        $entity = new Scalar()
            ->setLocale('en')
            ->setTitle('Test title')
            ->setShared('Shared attribute')
            ->setEmpty('Empty attribute');

        $this->entityManager->persist($entity);

        return $entity;
    }
}