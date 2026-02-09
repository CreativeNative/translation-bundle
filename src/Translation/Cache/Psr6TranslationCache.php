<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * PSR-6 adapter wrapping CacheItemPoolInterface.
 *
 * Note: Persistent PSR-6 backends (Redis, filesystem) may not serialize Doctrine
 * entities cleanly due to proxy objects and EntityManager references. The primary
 * use case is in-memory backends like Symfony's ArrayAdapter. For persistent
 * caching, consider storing identifiers only and letting Doctrine reload.
 */
final class Psr6TranslationCache implements TranslationCacheInterface
{
    private const string TRANSLATION_PREFIX = 'tmi_translation.';
    private const string IN_PROGRESS_PREFIX = 'tmi_in_progress.';

    public function __construct(
        private readonly CacheItemPoolInterface $cachePool,
    ) {
    }

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

        $value = $item->get();

        return $value instanceof TranslatableInterface ? $value : null;
    }

    public function set(string $tuuid, string $locale, TranslatableInterface $entity): void
    {
        $item = $this->cachePool->getItem($this->translationKey($tuuid, $locale));
        $item->set($entity);
        $this->cachePool->save($item);
    }

    public function markInProgress(string $tuuid, string $locale): void
    {
        $item = $this->cachePool->getItem($this->inProgressKey($tuuid, $locale));
        $item->set(true);
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
