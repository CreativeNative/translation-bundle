<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;

final class TranslatableEventSubscriber implements EventSubscriber
{
    public function __construct(
        #[Autowire(param: 'tmi_translation.default_locale')]
        private string $defaultLocale,
        private EntityTranslatorInterface $entityTranslator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::postLoad,
            Events::onFlush,
        ];
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof TranslatableInterface) {
            $entity->generateTuuid();
        }
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof TranslatableInterface) {
            return;
        }

        if (null === $entity->getLocale() || '' === $entity->getLocale()) {
            $entity->setLocale($this->defaultLocale);
        }

        $this->entityTranslator->afterLoad($entity);
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        assert($entityManager instanceof EntityManagerInterface);
        $uow = $entityManager->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof TranslatableInterface) {
                $this->entityTranslator->beforePersist($entity);
                $meta = $entityManager->getClassMetadata($entity::class);
                $uow->recomputeSingleEntityChangeSet($meta, $entity);
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof TranslatableInterface) {
                $this->entityTranslator->beforeUpdate($entity);
                $meta = $entityManager->getClassMetadata($entity::class);
                $uow->recomputeSingleEntityChangeSet($meta, $entity);
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof TranslatableInterface) {
                $this->entityTranslator->beforeRemove($entity);
            }
        }
    }
}
