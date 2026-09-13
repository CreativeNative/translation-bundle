<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\Root\Ambiguous;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Common\PlainRoot;

/**
 * Two root references on one class -- a translation row copies its tuuid from
 * exactly one root (TranslationRootContractException::forAmbiguousRootProperty).
 */
class AmbiguousRootEntity implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\ManyToOne(targetEntity: PlainRoot::class)]
    /** @phpstan-ignore property.onlyWritten, property.unusedType */
    private PlainRoot|null $first = null;

    #[ORM\ManyToOne(targetEntity: PlainRoot::class)]
    /** @phpstan-ignore property.onlyWritten, property.unusedType */
    private PlainRoot|null $second = null;
}
