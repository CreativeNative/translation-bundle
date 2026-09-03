<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Cache;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * Abstraction for translation caching and circular-reference detection.
 *
 * Implementations store translated entities keyed by tuuid+locale and track
 * in-progress translations to prevent infinite recursion.
 *
 * There is deliberately no has(): on a persistent backend key presence proves nothing --
 * a row deleted since it was cached, or an entry written in an older format, leaves the
 * key behind while a load fails. The only reliable existence
 * check is `get() !== null`, which also costs one round-trip instead of two. A has() the
 * bundle shipped up to v3.3.0 answered exactly that unreliable question and was removed in
 * v3.4.0 before any consumer adopted it (see UPGRADING.md).
 */
interface TranslationCacheInterface
{
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
     *
     * An in-progress mark is only meaningful for the duration of the translation frame
     * that set it; EntityTranslator always clears it in a finally. A cache hands back
     * managed instances or null; persistent backends must reload through the
     * EntityManager with the locale filter suspended -- markers are per-process by
     * definition and are never expected to outlive the request that set them.
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
