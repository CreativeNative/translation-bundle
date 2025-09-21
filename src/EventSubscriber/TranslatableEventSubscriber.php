<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

final class TranslatableEventSubscriber implements EventSubscriber
{
    /**
     * @return array<string, mixed>
     */
    public function getSubscribedEvents(): array
    {
        return [Events::prePersist];
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof TranslatableInterface) {
            $entity->generateTuuid();
        }
    }
}
