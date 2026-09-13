<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\Root\StiDedupe;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Common\PlainRoot;

/**
 * The SINGLE_TABLE dedupe fixture: an abstract ancestor declares ONE root reference
 * with a property-level error (unique join column) and non-nullable without a
 * constructor. Two concrete leaves inherit it. Expected: the property-level error
 * ONCE (deduplicated by declaring class), the constructor error once PER LEAF, and
 * nothing for the abstract parent (the pass skips abstract classes).
 */
abstract class AbstractStiRows implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\ManyToOne(targetEntity: PlainRoot::class)]
    #[ORM\JoinColumn(name: 'root_id', unique: true)]
    /** @phpstan-ignore property.unused */
    private PlainRoot $root;
}
