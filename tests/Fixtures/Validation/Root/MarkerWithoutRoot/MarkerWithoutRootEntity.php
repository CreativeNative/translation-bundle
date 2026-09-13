<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\Root\MarkerWithoutRoot;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\TranslationRoot;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Common\PlainRoot;

/**
 * #[TranslationRoot] on two properties that are NOT root references: a scalar, and
 * a OneToOne typed to a root (wrong association kind). Both are
 * TranslationRootContractException::forMarkerWithoutRoot.
 */
class MarkerWithoutRootEntity implements TranslatableInterface
{
    use TranslatableTrait;

    #[TranslationRoot]
    #[ORM\Column]
    /** @phpstan-ignore property.onlyWritten */
    private string $title = '';

    #[TranslationRoot]
    #[ORM\OneToOne(targetEntity: PlainRoot::class)]
    /** @phpstan-ignore property.onlyWritten, property.unusedType */
    private PlainRoot|null $oneToOne = null;
}
