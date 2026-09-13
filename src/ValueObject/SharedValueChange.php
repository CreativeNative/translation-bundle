<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\ValueObject;

/**
 * One shared value that differed between a sibling and its source: the path in the
 * notation of `tmi:translation:sync-shared`'s drift table, whether it is an association
 * (compared by identity, never cloned), the sibling's value before and the source's value
 * it was -- or, for a comparison, would be -- given.
 *
 * Carried by {@see SharedValueSyncReport::changes()} so the command can show an operator
 * exactly what a write mode overwrote (`-v`), and a consumer's own listener can log it.
 * Values are the raw property values; rendering them is the caller's business.
 */
final readonly class SharedValueChange
{
    public function __construct(
        public string $path,
        public bool $association,
        public mixed $old,
        public mixed $new,
    ) {
    }
}
