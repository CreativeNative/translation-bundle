<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Cache\TranslationCacheInterface;

/**
 * Forgets a translatable row's cache entry the moment Doctrine has deleted it.
 *
 * Without this, a translation that was created, flushed, removed and flushed again
 * is still an object in the translation cache. The flush nulled its generated id, so
 * the UnitOfWork reports it as STATE_NEW -- the very state a fresh, not-yet-persisted
 * clone has -- and `EntityTranslator` cannot tell the two apart. The next
 * `getOrTranslate()` would hand the dead instance back and `persist()` it as a new
 * row carrying the OLD content instead of translating the current source afresh.
 *
 * Always registered, independent of `cascade_remove_locale_variants`: eviction is
 * about the cache being truthful, not about how many rows a remove() touches.
 *
 * `postRemove`, not `preRemove`, because only then is the row gone. A transaction
 * rolled back after `postRemove` leaves the instance detached or new and its entry
 * gone -- the next hit for that pair is a cache miss that reloads through
 * `LocaleVariantFinder`, one query where a stale hit would have cost none. Accepted.
 */
#[AsDoctrineListener(event: Events::postRemove)]
final readonly class TranslationCacheEvictionListener
{
    public function __construct(
        private TranslationCacheInterface $cache,
    ) {
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof TranslatableInterface) {
            return;
        }

        $locale = $entity->getLocale();

        if (null === $locale) {
            return;
        }

        $this->cache->remove($entity->getTuuid()->getValue(), $locale);
    }
}
