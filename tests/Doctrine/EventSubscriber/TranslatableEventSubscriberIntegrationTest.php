<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\EventSubscriber;

use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\AbstractLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\Doctrine\EventSubscriber\TranslatableEventSubscriber;
use Tmi\TranslationBundle\Event\PreTranslateEvent;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\NonTranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

#[CoversClass(TranslatableEventSubscriber::class)]
final class TranslatableEventSubscriberIntegrationTest extends IntegrationTestCase
{
    private TranslatableEventSubscriber $subscriber;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriber = new TranslatableEventSubscriber('en_US');

        $this->entityManager()->getEventManager()->addEventListener([Events::prePersist, Events::postLoad, Events::onFlush], $this->subscriber);
    }

    public function testPrePersistGeneratesTuuid(): void
    {
        $entity = new Scalar();
        $entity->setTitle('Integration Test');

        $tuuidBefore = $entity->getTuuid();
        self::assertNotEmpty($tuuidBefore->__toString());

        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();

        // Verify Tuuid was generated and stored
        $tuuidAfter = $entity->getTuuid();
        self::assertTrue(Uuid::isValid($tuuidAfter->__toString()));
    }

    public function testPostLoadSetsDefaultLocale(): void
    {
        $entity = new Scalar();
        $entity->setTitle('Locale Test');
        $entity->setLocale(null);

        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $loaded = $this->entityManager()->find(Scalar::class, $entity->getId());
        self::assertNotNull($loaded, 'Entity should be found after persist+flush');

        self::assertSame('en_US', $loaded->getLocale());
    }

    /**
     * Negative proof for #19: the subscriber used to route every scheduled
     * insertion, update and deletion through EntityTranslator::beforePersist/
     * beforeUpdate/beforeRemove -- always a no-op, since prePersist/postLoad
     * already normalise the entity's own locale before onFlush ever runs, so
     * translate($e, $e->getLocale()) always hit the identity return. Against
     * the pre-WP13 subscriber, the PreTranslateEvent spy below would fire once
     * per flush (three); the current subscriber never reaches the translator.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function testOnFlushNeverInvokesTheTranslator(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $translations = 0;
        $dispatcher->addListener(PreTranslateEvent::class, static function () use (&$translations): void {
            ++$translations;
        });

        // --- Persist entity ---
        $entity = new Scalar();
        $entity->setTitle('Initial Title');

        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();

        // After flush, ID must exist
        self::assertNotNull($entity->getId(), 'ID should be assigned after flush');

        // --- Update entity ---
        $entity->setTitle('Updated Title');
        $this->entityManager()->flush();

        $entityId = $entity->getId();

        // --- Remove entity ---
        $this->entityManager()->remove($entity);
        $this->entityManager()->flush();

        // Verify entity is gone from database
        self::assertNull($this->entityManager()->find(Scalar::class, $entityId));

        self::assertSame(0, $translations, 'onFlush must never route an entity through the translator');
    }

    public function testTranslationCloningAndLocale(): void
    {
        $entity = new Scalar();
        $entity->setTitle('Translation Test');
        $entity->setLocale('en_US');

        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $loaded = $this->entityManager()->find(Scalar::class, $entity->getId());
        self::assertNotNull($loaded, 'Entity should be found after persist+flush');

        // Use translator to get translation for target locale
        $translation = $this->translator()->translate($loaded, 'de_DE');

        self::assertIsTranslation($loaded, $translation, 'de_DE');
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testNonTranslatableEntitiesAreIgnored(): void
    {
        $entity = new NonTranslatableManyToOneBidirectionalChild();

        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();

        $this->addToAssertionCount(1); // Just assert no exception is thrown
    }

    /**
     * Regression for the false orphan warning: a source created in a
     * non-default locale and translated before the flush shares its Tuuid with
     * the new variants — it must not be reported as an orphan.
     */
    public function testOrphanIsNotReportedWhenTranslationJoinsTheSameFlush(): void
    {
        $logger     = $this->createSpyLogger();
        $subscriber = new TranslatableEventSubscriber('en_US', $logger, false);
        $this->entityManager()->getEventManager()->addEventListener([Events::prePersist, Events::postLoad, Events::onFlush], $subscriber);

        $entity = new Scalar();
        $entity->setTitle('Non-default source');
        $entity->setLocale('de_DE');

        // Flag before the pipeline auto-generates the Tuuid, exactly as the
        // first-registered subscriber would observe the entity in production.
        $subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager()));

        $this->entityManager()->persist($entity);
        $translation = $this->translator()->translateAndPersist($entity, 'en_US');
        $this->entityManager()->flush();

        self::assertIsTranslation($entity, $translation, 'en_US');
        self::assertSame([], $logger->records, 'A source linked within the same flush must not be reported as an orphan');
    }

    public function testOrphanStillFlushedAloneIsReported(): void
    {
        $logger     = $this->createSpyLogger();
        $subscriber = new TranslatableEventSubscriber('en_US', $logger, false);
        $this->entityManager()->getEventManager()->addEventListener([Events::prePersist, Events::postLoad, Events::onFlush], $subscriber);

        $entity = new Scalar();
        $entity->setTitle('True orphan');
        $entity->setLocale('de_DE');

        $subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager()));

        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();

        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertStringContainsString('without a shared Tuuid', $logger->records[0]['message']);
        self::assertSame(['class' => Scalar::class, 'locale' => 'de_DE'], $logger->records[0]['context']);
    }

    /**
     * @return AbstractLogger&object{records: list<array{level: mixed, message: string, context: array<mixed>}>}
     */
    private function createSpyLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
            public array $records = [];

            /**
             * @param array<mixed> $context
             */
            #[\Override]
            public function log(mixed $level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };
    }
}
