<?php

namespace TMI\TranslationBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Umanit\DoctrineSingletonBundle\Event\FilterSingletonEvent;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * Filter on singletons if the UmanitDoctrineSingletonBundle is available.
 */
class SingletonSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [FilterSingletonEvent::SINGLETON_FILTER_EVENT => ['onFilterEvent']];
    }

    /**
     * Called to check if a singleton is unique per language.
     */
    public function onFilterEvent(FilterSingletonEvent $event): void
    {
        $entity  = $event->getEntity();
        $filters = $event->getFilters();

        if ($entity instanceof TranslatableInterface) {
            $filters['locale'] = $entity->getLocale();
        }

        $event->setFilters($filters);
    }
}
