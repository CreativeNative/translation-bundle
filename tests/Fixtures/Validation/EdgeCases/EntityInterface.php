<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\EdgeCases;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * Interface to test that interfaces are skipped.
 */
interface EntityInterface extends TranslatableInterface
{
}
