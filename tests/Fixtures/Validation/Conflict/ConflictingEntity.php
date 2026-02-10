<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\Conflict;

use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Test fixture with conflicting property attributes.
 */
class ConflictingEntity implements TranslatableInterface
{
    use TranslatableTrait;

    #[SharedAmongstTranslations]
    #[EmptyOnTranslate]
    /** @phpstan-ignore property.onlyWritten */
    private string $conflictingProperty = '';
}
