<?php

declare(strict_types=1);

namespace TMI\TranslationBundle\Test\Doctrine\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\UnitOfWork;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use TMI\TranslationBundle\Doctrine\EventSubscriber\TranslatableEventSubscriber;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Translation\EntityTranslatorInterface;

#[\PHPUnit\Framework\Attributes\CoversClass(\TMI\TranslationBundle\Doctrine\EventSubscriber\TranslatableEventSubscriber::class)]
final class TranslatableEventSubscriberTest extends TestCase
{
    private EntityTranslatorInterface&MockObject $translator;
    private TranslatableEventSubscriber $subscriber;

    public function setUp(): void
    {
        $this->translator = $this->createMock(EntityTranslatorInterface::class);
        $this->subscriber = new TranslatableEventSubscriber(
            'en',
            $this->translator
        );
    }

    public function testPostLoadSetsDefaultLocaleAndCallsAfterLoadWhenLocaleIsNull(): void
    {
        $entity = $this->createMock(TranslatableInterface::class);

        $entity->method('getLocale')->willReturn(null);
        $entity->expects($this->once())->method('setLocale')->with('en');

        $this->translator->expects($this->once())->method('afterLoad')->with($entity);

        $em = $this->createMock(EntityManagerInterface::class);
        $args = new PostLoadEventArgs($entity, $em);

        $this->subscriber->postLoad($args);
    }

    public function testPostLoadSetsDefaultLocaleAndCallsAfterLoadWhenLocaleIsEmptyString(): void
    {
        $entity = $this->createMock(TranslatableInterface::class);

        $entity->method('getLocale')->willReturn('');
        $entity->expects($this->once())->method('setLocale')->with('en');

        $this->translator->expects($this->once())->method('afterLoad')->with($entity);

        $em = $this->createMock(EntityManagerInterface::class);
        $args = new PostLoadEventArgs($entity, $em);

        $this->subscriber->postLoad($args);
    }

    public function testPostLoadDoesNotOverrideExistingLocaleButCallsAfterLoad(): void
    {
        $entity = $this->createMock(TranslatableInterface::class);

        $entity->method('getLocale')->willReturn('fr');
        $entity->expects($this->never())->method('setLocale');

        $this->translator->expects($this->once())->method('afterLoad')->with($entity);

        $em = $this->createMock(EntityManagerInterface::class);
        $args = new PostLoadEventArgs($entity, $em);

        $this->subscriber->postLoad($args);
    }

    public function testPostLoadIgnoresNonTranslatableEntities(): void
    {
        $entity = new stdClass();

        $em = $this->createMock(EntityManagerInterface::class);
        $args = new PostLoadEventArgs($entity, $em);

        $this->translator->expects($this->never())->method('afterLoad');

        $this->subscriber->postLoad($args);
    }

    public function testOnFlushCallsTranslatorForInsertUpdateDelete(): void
    {
        $entity = $this->createMock(TranslatableInterface::class);

        $uow = $this->createMock(UnitOfWork::class);
        $em  = $this->createMock(EntityManagerInterface::class);

        $uow->method('getScheduledEntityInsertions')->willReturn([$entity]);
        $uow->method('getScheduledEntityUpdates')->willReturn([$entity]);
        $uow->method('getScheduledEntityDeletions')->willReturn([$entity]);

        $em->method('getUnitOfWork')->willReturn($uow);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata($entity::class));

        $uow->expects($this->exactly(2))
            ->method('recomputeSingleEntityChangeSet')
            ->with(self::anything(), $entity);

        $this->translator->expects($this->once())->method('beforePersist')->with($entity, $em);
        $this->translator->expects($this->once())->method('beforeUpdate')->with($entity, $em);
        $this->translator->expects($this->once())->method('beforeRemove')->with($entity, $em);

        $args = new OnFlushEventArgs($em);
        $this->subscriber->onFlush($args);
    }

    public function testNonTranslatableEntitiesAreIgnored(): void
    {
        $entity = new stdClass();

        $uow = $this->createMock(UnitOfWork::class);
        $em  = $this->createMock(EntityManagerInterface::class);

        $uow->method('getScheduledEntityInsertions')->willReturn([$entity]);
        $uow->method('getScheduledEntityUpdates')->willReturn([$entity]);
        $uow->method('getScheduledEntityDeletions')->willReturn([$entity]);

        $em->method('getUnitOfWork')->willReturn($uow);

        $this->translator->expects($this->never())->method('beforePersist');
        $this->translator->expects($this->never())->method('beforeUpdate');
        $this->translator->expects($this->never())->method('beforeRemove');

        $args = new OnFlushEventArgs($em);
        $this->subscriber->onFlush($args);
    }
}
