<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Cache;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * PSR-6 adapter wrapping CacheItemPoolInterface.
 *
 * Entries hold the entity's class and identifier, never the entity itself: set() resolves
 * the identifier through Doctrine metadata and get() reloads through the EntityManager on
 * every hit. That is what makes this cache safe on persistent backends (Redis, filesystem,
 * ...) -- a serialized Doctrine entity would carry dead proxy/EntityManager references
 * across requests or processes, but an identifier reloads cleanly wherever it is read back.
 * Within a single request, Doctrine's identity map hands back the exact instance the
 * translation pipeline already produced, so nothing is lost for the in-memory case either.
 *
 * A row deleted after it was cached simply reloads to null, which get() reports as a cache
 * miss rather than handing back a stale or corrupted object -- same for a cache entry
 * written before this identifier-based format (an upgrade path from an older release, or
 * any other unrecognised value). An entity that has no identifier yet (not persisted, or
 * persisted but not flushed) cannot be reloaded later, so set() does not cache it.
 */
final class Psr6TranslationCache implements TranslationCacheInterface
{
    private const string TRANSLATION_PREFIX = 'tmi_translation.';
    private const string IN_PROGRESS_PREFIX = 'tmi_in_progress.';

    /**
     * Lifetime in seconds of an in-progress marker.
     *
     * An in-progress marker is only meaningful within the translation frame that set it,
     * and EntityTranslator clears it in a finally. The TTL is defence in depth for
     * persistent backends: should a marker ever survive (fatal error, killed worker), it
     * expires instead of blocking that tuuid/locale until the pool is purged by hand.
     */
    private const int IN_PROGRESS_TTL = 60;

    public function __construct(
        private readonly CacheItemPoolInterface $cachePool,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Only checks key presence in the pool -- it does not verify the referenced entity still
     * loads. See {@see TranslationCacheInterface::has()} for why `get() !== null` is the
     * reliable check on this implementation.
     */
    public function has(string $tuuid, string $locale): bool
    {
        return $this->cachePool->hasItem($this->translationKey($tuuid, $locale));
    }

    public function get(string $tuuid, string $locale): TranslatableInterface|null
    {
        $item = $this->cachePool->getItem($this->translationKey($tuuid, $locale));

        if (!$item->isHit()) {
            return null;
        }

        $reference = $item->get();
        $class     = \is_array($reference) ? ($reference['class'] ?? null) : null;
        $id        = \is_array($reference) ? ($reference['id'] ?? null) : null;

        if (!\is_string($class) || !class_exists($class) || !\is_array($id)) {
            return null;
        }

        $entity = $this->entityManager->find($class, $id);

        return $entity instanceof TranslatableInterface ? $entity : null;
    }

    public function set(string $tuuid, string $locale, TranslatableInterface $entity): void
    {
        $identifier = $this->entityManager->getClassMetadata($entity::class)->getIdentifierValues($entity);

        if ([] === $identifier) {
            // Not persisted (or persisted but not yet flushed): there is no stable
            // identifier to reload by later, so there is nothing useful to cache.
            return;
        }

        $item = $this->cachePool->getItem($this->translationKey($tuuid, $locale));
        $item->set(['class' => $entity::class, 'id' => $identifier]);
        $this->cachePool->save($item);
    }

    public function markInProgress(string $tuuid, string $locale): void
    {
        $item = $this->cachePool->getItem($this->inProgressKey($tuuid, $locale));
        $item->set(true);
        $item->expiresAfter(self::IN_PROGRESS_TTL);
        $this->cachePool->save($item);
    }

    public function unmarkInProgress(string $tuuid, string $locale): void
    {
        $this->cachePool->deleteItem($this->inProgressKey($tuuid, $locale));
    }

    public function isInProgress(string $tuuid, string $locale): bool
    {
        return $this->cachePool->hasItem($this->inProgressKey($tuuid, $locale));
    }

    /**
     * Generate a PSR-6-compliant key for translation cache entries.
     *
     * PSR-6 keys must match [A-Za-z0-9_.]{1,64}. UUID dashes are replaced
     * with underscores. Max length: 16 (prefix) + 32 (UUID) + 1 (dot) + 5 (locale) = 54 chars.
     */
    private function translationKey(string $tuuid, string $locale): string
    {
        return self::TRANSLATION_PREFIX.str_replace('-', '_', $tuuid).'.'.$locale;
    }

    /**
     * Generate a PSR-6-compliant key for in-progress tracking entries.
     *
     * Max length: 16 (prefix) + 32 (UUID) + 1 (dot) + 5 (locale) = 54 chars.
     */
    private function inProgressKey(string $tuuid, string $locale): string
    {
        return self::IN_PROGRESS_PREFIX.str_replace('-', '_', $tuuid).'.'.$locale;
    }
}
