<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Cache;

use Symfony\Contracts\Service\ResetInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * Array-based per-request cache implementation.
 *
 * Stores translations in nested arrays and in-progress flags in a flat array,
 * matching the original EntityTranslator caching behavior. A new instance per
 * request (the default, non-shared-worker case) already starts empty; reset()
 * additionally makes this safe under long-running workers (Messenger consumers,
 * FrameworkBundle's `services_resetter`), where the same instance survives
 * across requests and must not hand a later request an entity cached -- and
 * possibly since detached -- by an earlier one. services.yaml tags this
 * service `kernel.reset` explicitly: Symfony does not autoconfigure
 * ResetInterface into that tag.
 */
final class InMemoryTranslationCache implements TranslationCacheInterface, ResetInterface
{
    /** @var array<string, array<string, TranslatableInterface>> */
    private array $cache = [];

    /** @var array<string, true> */
    private array $inProgress = [];

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

    public function reset(): void
    {
        $this->cache      = [];
        $this->inProgress = [];
    }
}
