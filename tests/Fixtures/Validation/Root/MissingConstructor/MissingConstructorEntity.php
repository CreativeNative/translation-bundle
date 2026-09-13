<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\Root\MissingConstructor;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Common\PlainRoot;

/**
 * Phase 2 declared (non-nullable root reference) without the constructor that
 * requires the root (TranslationRootContractException::forMissingRootConstructorParameter).
 */
class MissingConstructorEntity implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\ManyToOne(targetEntity: PlainRoot::class)]
    /** @phpstan-ignore property.unused */
    private PlainRoot $root;
}
