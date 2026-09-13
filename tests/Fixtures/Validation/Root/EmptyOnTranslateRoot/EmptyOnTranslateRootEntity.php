<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\Root\EmptyOnTranslateRoot;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Common\PlainRoot;

/**
 * A root reference marked #[EmptyOnTranslate] -- emptying it would orphan the new
 * variant from its object (TranslationRootContractException::forEmptyOnTranslate).
 * Reported as the root-specific error, not as the generic Shared+Empty conflict.
 */
class EmptyOnTranslateRootEntity implements TranslatableInterface
{
    use TranslatableTrait;

    #[EmptyOnTranslate]
    #[ORM\ManyToOne(targetEntity: PlainRoot::class)]
    /** @phpstan-ignore property.onlyWritten, property.unusedType */
    private PlainRoot|null $root = null;
}
