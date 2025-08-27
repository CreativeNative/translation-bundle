<?php

namespace TMI\TranslationBundle\Test;

use Symfony\Component\HttpKernel\KernelInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Fixtures\Entity\CanNotBeNull;
use TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar;

/**
 * Test for scalar value.
 */
class ScalarTranslationTest extends AbstractBaseTest
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    public function testItCanTranslateScalarValue()
    {
        $entity = $this->createEntity();
        $translation = $this->translator->translate($entity, 'fr');
        $this->entityManager->flush();

        $this->assertObjectHasAttribute('title', $translation);
        $this->assertEquals('Test title', $translation->getTitle());
        $this->assertIsTranslation($entity, $translation);
    }


    /**
     * @todo fixme: This test is broken because of TranslatableEventSubscriber->alreadySyncedEntities. I don't know yet how to fix it.
     **/
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

    public function testItCanEmptyScalarValueOnTranslate()
    {
        $entity = $this->createEntity();
        $translation = $this->translator->translate($entity, 'fr');

        $this->entityManager->flush();

        $this->assertObjectHasAttribute('empty', $translation);
        $this->assertEmpty($translation->getEmpty());
        $this->assertIsTranslation($entity, $translation);
    }

    public function testItCanNotEmptyNotNullableScalarValueOnTranslate()
    {
        $this->expectException(\LogicException::class);

        $entity = new CanNotBeNull()->setEmptyNotNullable('Empty not nullable attribute');

        $this->entityManager->persist($entity);
        $translation = $this->translator->translate($entity, 'fr');

        $this->entityManager->flush();

        $this->assertObjectHasAttribute('empty_not_nullable', $translation);
        $this->assertNotEmpty($translation->getEmptyNotNullable());
        $this->assertIsTranslation($entity, $translation);
    }

    /**
     * Creates test entity.
     */
    protected function createEntity(): Scalar
    {
        $entity = new Scalar()
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
        $this->assertObjectHasAttribute('locale', $translation);
        $this->assertEquals('fr', $translation->getLocale());

        // TUUID assertion
        $this->assertObjectHasAttribute('tuuid', $translation);
        $this->assertEquals($source->getTuuid(), $translation->getTuuid());

        // Object identity assertion
        $this->assertNotSame($source, $translation);
    }
}