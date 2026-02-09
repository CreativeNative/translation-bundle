<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Cache;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * Array-based per-request cache implementation.
 *
 * Stores translations in nested arrays and in-progress flags in a flat array,
 * matching the original EntityTranslator caching behavior. The cache resets
 * automatically on each request since the service is instantiated per-request.
 */
final class InMemoryTranslationCache implements TranslationCacheInterface
{
    /** @var array<string, array<string, TranslatableInterface>> */
    private array $cache = [];

    /** @var array<string, true> */
    private array $inProgress = [];

    public function has(string $tuuid, string $locale): bool
    {
        return isset($this->cache[$tuuid][$locale]);
    }

    public function get(string $tuuid, string $locale): TranslatableInterface|null
    {
        return $this->cache[$tuuid][$locale] ?? null;
    }

    public function set(string $tuuid, string $locale, TranslatableInterface $entity): void
    {
        $this->cache[$tuuid][$locale] = $entity;
    }

    public function markInProgress(string $tuuid, string $locale): void
    {
        $this->inProgress[$tuuid.':'.$locale] = true;
    }

    public function unmarkInProgress(string $tuuid, string $locale): void
    {
        unset($this->inProgress[$tuuid.':'.$locale]);
    }

    public function isInProgress(string $tuuid, string $locale): bool
    {
        return isset($this->inProgress[$tuuid.':'.$locale]);
    }
}
