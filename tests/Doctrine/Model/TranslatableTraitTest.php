<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\Model;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Ramsey\Uuid\Uuid;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\IntegrationTestCase;

final class TranslatableTraitTest extends IntegrationTestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testPrePersistEvent(): void
    {
        $entity = new Scalar();
        $entity->setTitle('Test Entity');

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->assertNotNull($entity->getTuuid());
        $this->assertTrue(Uuid::isValid($entity->getTuuid()));
    }

    public function testGenerateTuuidOnNewEntity(): void
    {
        $entity = new Scalar();

        $this->assertNull($entity->getTuuid());

        $entity->generateTuuid();

        $this->assertNotNull($entity->getTuuid());

        $this->assertTrue(Uuid::isValid($entity->getTuuid()));

        $this->assertEquals(36, strlen($entity->getTuuid()));
    }

    public function testGenerateTuuidOnEntityWithExistingTuuid(): void
    {
        $entity = new Scalar();
        $existingUuid = Uuid::uuid4()->toString();

        $entity->setTuuid($existingUuid);

        $entity->generateTuuid();

        $this->assertEquals($existingUuid, $entity->getTuuid());
    }

    public function testLocaleMethods(): void
    {
        $entity = new Scalar();

        $entity->setLocale('de');
        $this->assertEquals('de', $entity->getLocale());

        $entity->setLocale(null);
        $this->assertNull($entity->getLocale());
    }

    public function testTranslationMethods(): void
    {
        $entity = new Scalar();

        $translations = ['de' => ['title' => 'Titel'], 'en' => ['title' => 'Title']];
        $entity->setTranslations($translations);
        $this->assertEquals($translations, $entity->getTranslations());

        $entity->setTranslation('fr', ['title' => 'Titre']);
        $this->assertEquals(['title' => 'Titre'], $entity->getTranslation('fr'));

        $this->assertNull($entity->getTranslation('es'));
    }

    public function testTuuidMethods(): void
    {
        $entity = new Scalar();
        $uuid = Uuid::uuid4()->toString();

        $entity->setTuuid($uuid);
        $this->assertEquals($uuid, $entity->getTuuid());

        $entity->setTuuid(null);
        $this->assertNull($entity->getTuuid());
    }
}
