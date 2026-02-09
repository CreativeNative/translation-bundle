<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Cache;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * Abstraction for translation caching and circular-reference detection.
 *
 * Implementations store translated entities keyed by tuuid+locale and track
 * in-progress translations to prevent infinite recursion.
 */
interface TranslationCacheInterface
{
    /**
     * Check if a translation exists in cache.
     */
    public function has(string $tuuid, string $locale): bool;

    /**
     * Get a cached translation. Returns null if not cached.
     */
    public function get(string $tuuid, string $locale): TranslatableInterface|null;

    /**
     * Store a translation in cache.
     */
    public function set(string $tuuid, string $locale, TranslatableInterface $entity): void;

    /**
     * Mark a tuuid+locale as currently being translated (cycle detection).
     */
    public function markInProgress(string $tuuid, string $locale): void;

    /**
     * Remove the in-progress mark for a tuuid+locale.
     */
    public function unmarkInProgress(string $tuuid, string $locale): void;

    /**
     * Check if a tuuid+locale is currently being translated.
     */
    public function isInProgress(string $tuuid, string $locale): bool;
}
