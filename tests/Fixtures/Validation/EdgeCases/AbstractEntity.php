<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\EdgeCases;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * Abstract entity to test that abstract classes are skipped.
 */
abstract class AbstractEntity implements TranslatableInterface
{
}
