<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\EdgeCases;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Regression fixture for issue #2: extraction must return every class a file
 * declares, not just the first — a regex-based extractor stops at the first
 * "class <word>" match and would silently miss the second class below.
 */
class MultipleClassesHelper
{
}

/**
 * The translatable entity declared second in this file.
 */
class MultipleClassesEntity implements TranslatableInterface
{
    use TranslatableTrait;
}
