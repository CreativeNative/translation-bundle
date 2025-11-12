<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\Model;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

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

        $this->assertNotInstanceOf(Tuuid::class, $entity->getTuuid());

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->assertInstanceOf(Tuuid::class, $entity->getTuuid());
        $this->assertInstanceOf(Tuuid::class, $entity->getTuuid());
        $this->assertTrue(Uuid::isValid($entity->getTuuid()->__toString()));
    }

    public function testSetInvalidTuuidThrowsException(): void
    {
        $invalidTuuid = 'not-a-valid-uuid';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Invalid Tuuid value: "%s"', $invalidTuuid));

        // Invalid TUuID wird nun direkt im ValueObject validiert
        new Tuuid($invalidTuuid);
    }

    public function testLocaleMethods(): void
    {
        $entity = new Scalar();

        $entity->setLocale('de_DE');
        $this->assertSame('de_DE', $entity->getLocale());

        $entity->setLocale();
        $this->assertNull($entity->getLocale());
    }

    public function testTranslationMethods(): void
    {
        $entity = new Scalar();

        $translations = ['de_DE' => ['title' => 'Titel'], 'en_US' => ['title' => 'Title']];
        $entity->setTranslations($translations);
        $this->assertSame($translations, $entity->getTranslations());

        $entity->setTranslation('fr', ['title' => 'Titre']);
        $this->assertSame(['title' => 'Titre'], $entity->getTranslation('fr'));

        $this->assertNull($entity->getTranslation('es'));
    }

    public function testTuuidMethods(): void
    {
        $entity = new Scalar();
        $tuuid  = new Tuuid(Uuid::v4()->toRfc4122());

        $entity->setTuuid($tuuid);
        $this->assertSame($tuuid, $entity->getTuuid());
        $this->assertSame((string) $tuuid, $entity->getTuuid()->__toString());

        $entity->setTuuid(null);
        $this->assertNull($entity->getTuuid());
    }

    public function testGenerateTuuid(): void
    {
        $entity = new Scalar();
        $this->assertNotInstanceOf(Tuuid::class, $entity->getTuuid());

        $entity->generateTuuid();
        $this->assertInstanceOf(Tuuid::class, $entity->getTuuid());
        $this->assertTrue(Uuid::isValid($entity->getTuuid()->__toString()));

        // Generate again should not overwrite existing TUuID
        $existing = $entity->getTuuid();
        $entity->generateTuuid();
        $this->assertSame($existing, $entity->getTuuid());
    }
}
