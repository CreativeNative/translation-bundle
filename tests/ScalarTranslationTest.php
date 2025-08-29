<?php

namespace TMI\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use LogicException;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Fixtures\Entity\CanNotBeNull;
use TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar;

/**
 * Test for scalar value.
 */
class ScalarTranslationTest extends TestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateScalarValue(): void
    {
        $entity = $this->createEntity();
        $translation = $this->translator->translate($entity, 'fr');
        $this->entityManager->flush();

//        $this->assertObjectHasAttribute('title', $translation);
        $this->assertEquals('Test title', $translation->getTitle());
        $this->assertIsTranslation($entity, $translation);
    }


    /**
     * @todo fixme: This test is broken because of TranslatableEventSubscriber->alreadySyncedEntities. I don't know yet how to fix it.
     *
     * @throws ORMException
     */
//    public function testItCanShareScalarValueAmongstTranslations()
//    {
//        $entity = $this->createEntity();
//        /** @var Scalar $translation */
//        $translation = $this->translator->translate($entity, 'fr');
//        $this->entityManager->persist($translation);
//        $this->entityManager->flush();
//
//        // Update shared attribute
//        $translation->setShared('Updated shared');
//        $this->entityManager->persist($translation);
//        $this->entityManager->flush();
//
//        $this->assertObjectHasAttribute('shared', $entity);
//        $this->assertEquals('Updated shared', $entity->getShared());
//        $this->assertIsTranslation($entity, $translation);
//    }

    public function testItCanEmptyScalarValueOnTranslate(): void
    {
        $entity = $this->createEntity();
        $translation = $this->translator->translate($entity, 'fr');

        $this->entityManager->flush();

//        $this->assertObjectHasAttribute('empty', $translation);
        $this->assertEmpty($translation->getEmpty());
        $this->assertIsTranslation($entity, $translation);
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

        $this->assertTrue(
            property_exists($translation, 'empty_not_nullable'),
            'Property "empty_not_nullable" not found in translation object'
        );
        $this->assertNotNull($translation->getEmptyNotNullable());
        $this->assertNotEmpty($translation->getEmptyNotNullable());
        $this->assertIsTranslation($entity, $translation);
    }

    /**
     * Creates test entity.
     * @throws ORMException
     */
    protected function createEntity(): Scalar
    {
        $entity = new Scalar()->setLocale('en')
            ->setTitle('Test title')
            ->setShared('Shared attribute')
            ->setEmpty('Empty attribute');

        $this->entityManager->persist($entity);

        return $entity;
    }

    /**
     * Assert a translation is actually a translation.
     */
    protected function assertIsTranslation(TranslatableInterface $source, TranslatableInterface $translation): void
    {
        // Locale assertion
//        $this->assertObjectHasAttribute('locale', $translation);
        $this->assertSame('fr', $translation->getLocale());

        // TUUID assertion
//        $this->assertObjectHasAttribute('tuuid', $translation);
        $this->assertEquals($source->getTuuid(), $translation->getTuuid());

        // Object identity assertion
        $this->assertNotSame($source, $translation);
    }
}