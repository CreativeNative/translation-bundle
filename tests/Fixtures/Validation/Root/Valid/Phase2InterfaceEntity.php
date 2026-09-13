<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\Root\Valid;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;

/**
 * Phase 2 with the property AND the constructor parameter typed to the bare
 * interface -- a legitimate declaration `class_exists()` would have refused.
 */
class Phase2InterfaceEntity implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\ManyToOne(targetEntity: TranslationRootInterface::class)]
    /** @phpstan-ignore property.onlyWritten */
    private TranslationRootInterface $root;

    public function __construct(TranslationRootInterface $root)
    {
        $this->root = $root;
    }
}
