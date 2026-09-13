<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\Root\IdRoot;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Common\PlainRoot;

/**
 * A root reference that is part of the identifier -- PrimaryKeyHandler nulls it on
 * every clone (TranslationRootContractException::forIdRootProperty).
 */
class IdRootEntity implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: PlainRoot::class)]
    /** @phpstan-ignore property.onlyWritten, property.unusedType */
    private PlainRoot|null $root = null;
}
