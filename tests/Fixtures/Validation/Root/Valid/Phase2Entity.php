<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\Root\Valid;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Common\PlainRoot;

/**
 * Phase 2: non-nullable root reference, constructor requires the concrete root.
 */
class Phase2Entity implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\ManyToOne(targetEntity: PlainRoot::class)]
    #[ORM\JoinColumn(name: 'root_id', nullable: false)]
    /** @phpstan-ignore property.onlyWritten */
    private PlainRoot $root;

    public function __construct(string $title, PlainRoot $root)
    {
        $this->root = $root;
        $this->setLocale($title);
    }
}
