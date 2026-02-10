<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\EdgeCases;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Trait to test that traits are skipped.
 *
 * @phpstan-ignore trait.unused
 */
trait EntityTrait
{
    use TranslatableTrait;
}
