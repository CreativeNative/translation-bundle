<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\ValueObject;

/**
 * Outcome of reconciling one sibling locale variant against a source row:
 * which shared property paths differed and were (or, for a comparison,
 * would be) written, which differed but cannot be written because the
 * property is readonly, and which differed but must NOT be written because
 * the property is a translation root reference (5.1).
 *
 * Produced by {@see \Tmi\TranslationBundle\Doctrine\SharedValueSynchronizer}.
 * Paths use the notation of `tmi:translation:sync-shared`'s drift table:
 * `field` for a mapped column or a single-valued association,
 * `embedded.field` for a property inside an embeddable, and `embedded` for an
 * embeddable shared as a whole.
 */
final readonly class SharedValueSyncReport
{
    /**
     * @param list<string> $changed       property paths that differed and are writable
     * @param list<string> $readonlyDrift property paths that differed but are readonly
     * @param list<string> $rootDrift     root references that differ between the siblings --
     *                                    never written by the shared-value machinery, only by
     *                                    `tmi:translation:adopt-root`'s own classification
     */
    public function __construct(
        private array $changed,
        private array $readonlyDrift,
        private array $rootDrift = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function changed(): array
    {
        return $this->changed;
    }

    /**
     * @return list<string>
     */
    public function readonlyDrift(): array
    {
        return $this->readonlyDrift;
    }

    /**
     * @return list<string>
     */
    public function rootDrift(): array
    {
        return $this->rootDrift;
    }

    public function hasChanges(): bool
    {
        return [] !== $this->changed;
    }
}
