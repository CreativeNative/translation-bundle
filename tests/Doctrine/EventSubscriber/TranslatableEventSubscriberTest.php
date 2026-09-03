<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
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
use Tmi\TranslationBundle\ValueObject\Tuuid;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(TranslatableEventSubscriber::class)]
final class TranslatableEventSubscriberTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private TranslatableEventSubscriber $subscriber;

    public function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->subscriber    = new TranslatableEventSubscriber('en_US');
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

    public function testPrePersistDoesNotReportOrphans(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        // Even strict mode must not throw at persist time — the Tuuid generated
        // here may still be adopted by a translation created later in the flush.
        $subscriber = new TranslatableEventSubscriber('en_US', $logger, true);

        $entity = new Scalar();
        $entity->setLocale('de_DE');

        $subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager));

        self::assertTrue($entity->hasTuuid());
    }

    public function testOnFlushThrowsOnOrphanWhenStrict(): void
    {
        $subscriber = new TranslatableEventSubscriber('en_US', null, true);

        $entity = new Scalar();
        $entity->setLocale('de_DE');

        $subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager));

        self::expectException(OrphanTranslationException::class);
        self::expectExceptionMessage(Scalar::class);

        $this->flushInsertions($subscriber, [$entity]);
    }

    public function testOnFlushWarnsOnOrphanWhenNotStrict(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                self::stringContains('without a shared Tuuid'),
                ['class' => Scalar::class, 'locale' => 'de_DE'],
            );

        $subscriber = new TranslatableEventSubscriber('en_US', $logger, false);

        $entity = new Scalar();
        $entity->setLocale('de_DE');

        $subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager));

        // An unrelated insertion with its own Tuuid must not silence the report.
        $unrelated = new Scalar();
        $unrelated->setTuuid(Tuuid::generate());

        $this->flushInsertions($subscriber, [$entity, $unrelated]);

        // Tuuid is still generated so the row remains persistable.
        self::assertTrue($entity->hasTuuid());
    }

    public function testOnFlushToleratesOrphanWithoutLogger(): void
    {
        $entity = new Scalar();
        $entity->setLocale('de_DE');

        // Default subscriber: logger null, not strict — must neither throw nor fail.
        $this->subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager));
        $this->flushInsertions($this->subscriber, [$entity]);

        self::assertTrue($entity->hasTuuid());
    }

    public function testOnFlushDoesNotReportWhenTuuidAdoptedInSameFlush(): void
    {
        $subscriber = new TranslatableEventSubscriber('en_US', null, true);

        $entity = new Scalar();
        $entity->setLocale('de_DE');

        $subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager));

        // A translation cloned from the flagged entity carries its Tuuid — the
        // exact shape translate() + persist() produce within a single flush.
        $translation = new Scalar();
        $translation->setTuuid($entity->getTuuid());
        $translation->setLocale('en_US');

        // Insertions the shared-Tuuid scan must skip: a non-translatable and a
        // translatable that has no Tuuid assigned yet.
        $blank = new Scalar();

        $this->flushInsertions($subscriber, [$entity, new \stdClass(), $blank, $translation]);

        self::assertSame((string) $entity->getTuuid(), (string) $translation->getTuuid());
    }

    public function testOnFlushReportsOrphanOnlyOnce(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $subscriber = new TranslatableEventSubscriber('en_US', $logger, false);

        $entity = new Scalar();
        $entity->setLocale('de_DE');

        $subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager));

        $this->flushInsertions($subscriber, [$entity]);
        $this->flushInsertions($subscriber, [$entity]);
    }

    public function testOnFlushDoesNotReportWhenLocaleResetToDefault(): void
    {
        $subscriber = new TranslatableEventSubscriber('en_US', null, true);

        $entity = new Scalar();
        $entity->setLocale('de_DE');

        $subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager));

        // The locale was corrected between persist() and flush().
        $entity->setLocale('en_US');

        $this->flushInsertions($subscriber, [$entity]);

        self::assertSame('en_US', $entity->getLocale());
    }

    public function testPrePersistDoesNotFlagDefaultLocale(): void
    {
        $subscriber = new TranslatableEventSubscriber('en_US', null, true);

        $entity = new Scalar();
        $entity->setLocale('en_US');

        $subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager));
        $this->flushInsertions($subscriber, [$entity]);

        self::assertTrue($entity->hasTuuid());
    }

    public function testPrePersistDoesNotFlagNullLocale(): void
    {
        $subscriber = new TranslatableEventSubscriber('en_US', null, true);

        $entity = new Scalar();

        $subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager));
        $this->flushInsertions($subscriber, [$entity]);

        self::assertSame('en_US', $entity->getLocale());
    }

    public function testPrePersistDoesNotFlagWhenTuuidAlreadyShared(): void
    {
        $subscriber = new TranslatableEventSubscriber('en_US', null, true);

        $entity = new Scalar();
        $entity->setTuuid(Tuuid::generate());
        $entity->setLocale('de_DE');

        $subscriber->prePersist(new PrePersistEventArgs($entity, $this->entityManager));
        $this->flushInsertions($subscriber, [$entity]);

        self::assertSame('de_DE', $entity->getLocale());
    }

    public function testPostLoadSetsDefaultLocaleWhenLocaleIsNull(): void
    {
        $entity = $this->createMock(TranslatableInterface::class);

        $entity->method('getLocale')->willReturn(null);
        $entity->expects($this->once())->method('setLocale')->with('en_US');

        $args = new PostLoadEventArgs($entity, $this->entityManager);

        $this->subscriber->postLoad($args);
    }

    public function testPostLoadSetsDefaultLocaleWhenLocaleIsEmptyString(): void
    {
        $entity = $this->createMock(TranslatableInterface::class);

        $entity->method('getLocale')->willReturn('');
        $entity->expects($this->once())->method('setLocale')->with('en_US');

        $args = new PostLoadEventArgs($entity, $this->entityManager);

        $this->subscriber->postLoad($args);
    }

    public function testPostLoadDoesNotOverrideExistingLocale(): void
    {
        $entity = $this->createMock(TranslatableInterface::class);

        $entity->method('getLocale')->willReturn('fr');
        $entity->expects($this->never())->method('setLocale');

        $args = new PostLoadEventArgs($entity, $this->entityManager);

        $this->subscriber->postLoad($args);
    }

    public function testPostLoadIgnoresNonTranslatableEntities(): void
    {
        $entity = new \stdClass();

        $args = new PostLoadEventArgs($entity, $this->entityManager);

        $this->subscriber->postLoad($args);

        // Just assert that no exception was thrown and the method completed
        $this->addToAssertionCount(1);
    }

    /**
     * The subscriber used to route every scheduled insertion, update and
     * deletion through EntityTranslator::beforePersist/beforeUpdate/
     * beforeRemove and then recompute its change set — a guaranteed no-op,
     * since prePersist/postLoad already normalise the entity's own locale
     * before onFlush ever runs, so translate($e, $e->getLocale()) always hit
     * the identity return. onFlush now reads only the scheduled insertions
     * (for the orphan scan) and touches nothing else on the UnitOfWork.
     */
    public function testOnFlushOnlyReadsScheduledInsertions(): void
    {
        $entity = $this->createMock(TranslatableInterface::class);

        $uow = $this->createMock(UnitOfWork::class);
        $uow->expects($this->once())->method('getScheduledEntityInsertions')->willReturn([$entity, new \stdClass()]);
        $uow->expects($this->never())->method('getScheduledEntityUpdates');
        $uow->expects($this->never())->method('getScheduledEntityDeletions');
        $uow->expects($this->never())->method('recomputeSingleEntityChangeSet');

        $this->entityManager->method('getUnitOfWork')->willReturn($uow);

        $args = new OnFlushEventArgs($this->entityManager);
        $this->subscriber->onFlush($args);
    }

    /**
     * Runs onFlush with the given entities scheduled for insertion.
     *
     * @param list<object> $insertions
     */
    private function flushInsertions(TranslatableEventSubscriber $subscriber, array $insertions): void
    {
        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('getScheduledEntityInsertions')->willReturn($insertions);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($uow);

        $subscriber->onFlush(new OnFlushEventArgs($entityManager));
    }
}
