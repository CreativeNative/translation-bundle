<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\Valid;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Valid test fixture entity for compiler pass positive test.
 * Not a real Doctrine entity (no #[ORM\Entity] attribute).
 */
class ValidEntity implements TranslatableInterface
{
    use TranslatableTrait;

    /** @phpstan-ignore property.onlyWritten, property.unusedType */
    private int|null $id = null;

    /** @phpstan-ignore property.onlyWritten */
    private string $title = '';
}
