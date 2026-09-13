<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\Root\Common;

use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootTrait;

/**
 * A plain translation root for the compiler-pass fixtures. Not a real Doctrine
 * entity (no #[ORM\Entity]); the pass reflects, never maps.
 */
class PlainRoot implements TranslationRootInterface
{
    use TranslationRootTrait;
}
