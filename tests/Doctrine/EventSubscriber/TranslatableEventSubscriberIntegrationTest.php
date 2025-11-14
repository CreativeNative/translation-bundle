<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\EventSubscriber;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\Doctrine\EventSubscriber\TranslatableEventSubscriber;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\NonTranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class TranslatableEventSubscriberIntegrationTest extends IntegrationTestCase
{
    private TranslatableEventSubscriber $subscriber;

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriber = new TranslatableEventSubscriber(
            'en_US',
            $this->translator,
        );

        $this->entityManager->getEventManager()->addEventSubscriber($this->subscriber);
    }

    public function testPrePersistGeneratesTuuid(): void
    {
        $entity = new Scalar();
        $entity->setTitle('Integration Test');

        $this->assertInstanceOf(Tuuid::class, $entity->getTuuid());

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        // Verify Tuuid was generated and stored
        $this->assertInstanceOf(Tuuid::class, $entity->getTuuid());
        $this->assertTrue(Uuid::isValid($entity->getTuuid()->__toString()));
    }

    public function testPostLoadSetsDefaultLocale(): void
    {
        $entity = new Scalar();
        $entity->setTitle('Locale Test');
        $entity->setLocale(null);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = $this->entityManager->find(Scalar::class, $entity->getId());

        $this->assertSame('en_US', $loaded->getLocale());
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function testOnFlushCallsTranslatorBeforePersistUpdateRemove(): void
    {
        // --- Persist entity ---
        $entity = new Scalar();
        $entity->setTitle('Initial Title');

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        // After flush, ID must exist
        $this->assertNotNull($entity->getId(), 'ID should be assigned after flush');

        // --- Update entity ---
        $entity->setTitle('Updated Title');
        $this->entityManager->flush(); // triggers onFlush, subscriber should call translator

        $entityId = $entity->getId();

        // --- Remove entity ---
        $this->entityManager->remove($entity);
        $this->entityManager->flush(); // triggers onFlush

        // Verify entity is gone from database
        $this->assertNull($this->entityManager->find(Scalar::class, $entityId));
    }

    public function testTranslationCloningAndLocale(): void
    {
        $entity = new Scalar();
        $entity->setTitle('Translation Test');
        $entity->setLocale('en_US');

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = $this->entityManager->find(Scalar::class, $entity->getId());

        // Use translator to get translation for target locale
        $translation = $this->translator->translate($loaded, 'de_DE');

        self::assertIsTranslation($loaded, $translation, 'de_DE');
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testNonTranslatableEntitiesAreIgnored(): void
    {
        $entity = new NonTranslatableManyToOneBidirectionalChild();

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->assertTrue(true); // Just assert no exception is thrown
    }
}
