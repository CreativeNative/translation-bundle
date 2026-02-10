<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\EdgeCases;

/**
 * Non-translatable entity to test that non-TranslatableInterface classes are skipped.
 */
class NonTranslatableEntity
{
    /** @phpstan-ignore property.onlyWritten, property.unusedType */
    private int|null $id = null;
}
