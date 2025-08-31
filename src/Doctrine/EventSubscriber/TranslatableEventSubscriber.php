<?php

declare(strict_types=1);

namespace TMI\TranslationBundle\Doctrine\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Translation\EntityTranslatorInterface;

#[AsDoctrineListener(event: Events::postLoad)]
#[AsDoctrineListener(event: Events::onFlush)]
final class TranslatableEventSubscriber
{
    public function __construct(
        #[Autowire(param: 'tmi_translation.default_locale')]
        private readonly string $defaultLocale,
        private readonly EntityTranslatorInterface $entityTranslator,
    ) {
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
        $em  = $args->getObjectManager();
        \assert($em instanceof EntityManagerInterface);
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof TranslatableInterface) {
                $this->entityTranslator->beforePersist($entity, $em);
                $meta = $em->getClassMetadata($entity::class);
                $uow->recomputeSingleEntityChangeSet($meta, $entity);
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof TranslatableInterface) {
                $this->entityTranslator->beforeUpdate($entity, $em);
                $meta = $em->getClassMetadata($entity::class);
                $uow->recomputeSingleEntityChangeSet($meta, $entity);
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof TranslatableInterface) {
                $this->entityTranslator->beforeRemove($entity, $em);
            }
        }
    }
}
