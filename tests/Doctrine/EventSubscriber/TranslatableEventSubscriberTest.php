<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\Doctrine\EventSubscriber\TranslatableEventSubscriber;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Exception\OrphanTranslationException;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Tmi\TranslationBundle\ValueObject\Tuuid;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(TranslatableEventSubscriber::class)]
final class TranslatableEventSubscriberTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private EntityTranslatorInterface&MockObject $translator;
    private TranslatableEventSubscriber $subscriber;

    public function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->translator    = $this->createMock(EntityTranslatorInterface::class);
        $this->subscriber    = new TranslatableEventSubscriber(
            'en_US',
            $this->translator,
        );
    }

    public function testPrePersistGeneratesTuuidForTranslatableEntities(): void
    {
        // Use a real entity that implements TranslatableInterface instead of a mock
        $entity = new Scalar();

        // tuuid should be initialised
        self::assertNotEmpty($entity->getTuuid()->__toString());

        $args = new PrePersistEventArgs($entity, $this->entityManager);

        $this->subscriber->prePersist($args);

        // After prePersist, tuuid value should be valid
        $tuuidValue = $entity->getTuuid()->getValue();
        self::assertTrue(Uuid::isValid($tuuidValue));
    }

    public function testPrePersistIgnoresNonTranslatableEntities(): void
    {
        $entity = new \stdClass(); // Non-translatable entity

        $args = new PrePersistEventArgs($entity, $this->entityManager);

        // No methods should be called on non-translatable entities
        // For non-translatable entities, nothing should happen, so no expectations needed

        $this->subscriber->prePersist($args);

        // Just assert that no exception was thrown and the method completed
        $this->addToAssertionCount(1);
    }

    public function testPrePersistThrowsOnOrphanWhenStrict(): void
    {
        $subscriber = new TranslatableEventSubscriber('en_US', $this->translator, null, true);

        $entity = new Scalar();
        $entity->setLocale('de_DE');

        $args = new PrePersistEventArgs($entity, $this->entityManager);

        self::expectException(OrphanTranslationException::class);
        self::expectExceptionMessage(Scalar::class);

        $subscriber->prePersist($args);
    }

    public function testPrePersistWarnsOnOrphanWhenNotStrict(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                self::stringContains('without a shared Tuuid'),
                ['class' => Scalar::class, 'locale' => 'de_DE'],
            );

        $subscriber = new TranslatableEventSubscriber('en_US', $this->translator, $logger, false);

        $entity = new Scalar();
        $entity->setLocale('de_DE');

        $subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager));

        // Tuuid is still generated so the row remains persistable.
        self::assertTrue($entity->hasTuuid());
    }

    public function testPrePersistToleratesOrphanWithoutLogger(): void
    {
        $entity = new Scalar();
        $entity->setLocale('de_DE');

        // Default subscriber: logger null, not strict — must neither throw nor fail.
        $this->subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager));

        self::assertTrue($entity->hasTuuid());
    }

    public function testPrePersistDoesNotFlagDefaultLocale(): void
    {
        $subscriber = new TranslatableEventSubscriber('en_US', $this->translator, null, true);

        $entity = new Scalar();
        $entity->setLocale('en_US');

        $subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager));

        self::assertTrue($entity->hasTuuid());
    }

    public function testPrePersistDoesNotFlagNullLocale(): void
    {
        $subscriber = new TranslatableEventSubscriber('en_US', $this->translator, null, true);

        $entity = new Scalar();

        $subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager));

        self::assertSame('en_US', $entity->getLocale());
    }

    public function testPrePersistDoesNotFlagWhenTuuidAlreadyShared(): void
    {
        $subscriber = new TranslatableEventSubscriber('en_US', $this->translator, null, true);

        $entity = new Scalar();
        $entity->setTuuid(Tuuid::generate());
        $entity->setLocale('de_DE');

        $subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager));

        self::assertSame('de_DE', $entity->getLocale());
    }

    public function testPostLoadSetsDefaultLocaleAndCallsAfterLoadWhenLocaleIsNull(): void
    {
        $entity = $this->createMock(TranslatableInterface::class);

        $entity->method('getLocale')->willReturn(null);
        $entity->expects($this->once())->method('setLocale')->with('en_US');

        $this->translator->expects($this->once())->method('afterLoad')->with($entity);

        $args = new PostLoadEventArgs($entity, $this->entityManager);

        $this->subscriber->postLoad($args);
    }

    public function testPostLoadSetsDefaultLocaleAndCallsAfterLoadWhenLocaleIsEmptyString(): void
    {
        $entity = $this->createMock(TranslatableInterface::class);

        $entity->method('getLocale')->willReturn('');
        $entity->expects($this->once())->method('setLocale')->with('en_US');

        $this->translator->expects($this->once())->method('afterLoad')->with($entity);

        $args = new PostLoadEventArgs($entity, $this->entityManager);

        $this->subscriber->postLoad($args);
    }

    public function testPostLoadDoesNotOverrideExistingLocaleButCallsAfterLoad(): void
    {
        $entity = $this->createMock(TranslatableInterface::class);

        $entity->method('getLocale')->willReturn('fr');
        $entity->expects($this->never())->method('setLocale');

        $this->translator->expects($this->once())->method('afterLoad')->with($entity);

        $args = new PostLoadEventArgs($entity, $this->entityManager);

        $this->subscriber->postLoad($args);
    }

    public function testPostLoadIgnoresNonTranslatableEntities(): void
    {
        $entity = new \stdClass();

        $args = new PostLoadEventArgs($entity, $this->entityManager);

        $this->translator->expects($this->never())->method('afterLoad');

        $this->subscriber->postLoad($args);
    }

    public function testOnFlushCallsTranslatorForInsertUpdateDelete(): void
    {
        $entity = $this->createMock(TranslatableInterface::class);

        $uow = $this->createMock(UnitOfWork::class);

        $uow->method('getScheduledEntityInsertions')->willReturn([$entity]);
        $uow->method('getScheduledEntityUpdates')->willReturn([$entity]);
        $uow->method('getScheduledEntityDeletions')->willReturn([$entity]);

        $this->entityManager->method('getUnitOfWork')->willReturn($uow);
        $this->entityManager->method('getClassMetadata')->willReturn(new ClassMetadata($entity::class));

        $uow->expects($this->exactly(2))
            ->method('recomputeSingleEntityChangeSet')
            ->with(self::anything(), $entity);

        $this->translator->expects($this->once())->method('beforePersist')->with($entity);
        $this->translator->expects($this->once())->method('beforeUpdate')->with($entity);
        $this->translator->expects($this->once())->method('beforeRemove')->with($entity);

        $args = new OnFlushEventArgs($this->entityManager);
        $this->subscriber->onFlush($args);
    }

    public function testNonTranslatableEntitiesAreIgnored(): void
    {
        $entity = new \stdClass();

        $uow = $this->createMock(UnitOfWork::class);

        $uow->method('getScheduledEntityInsertions')->willReturn([$entity]);
        $uow->method('getScheduledEntityUpdates')->willReturn([$entity]);
        $uow->method('getScheduledEntityDeletions')->willReturn([$entity]);

        $this->entityManager->method('getUnitOfWork')->willReturn($uow);

        $this->translator->expects($this->never())->method('beforePersist');
        $this->translator->expects($this->never())->method('beforeUpdate');
        $this->translator->expects($this->never())->method('beforeRemove');

        $args = new OnFlushEventArgs($this->entityManager);
        $this->subscriber->onFlush($args);
    }
}
