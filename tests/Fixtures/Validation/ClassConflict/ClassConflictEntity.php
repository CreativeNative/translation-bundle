<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\ClassConflict;

use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Test fixture with class-level attribute conflict.
 */
#[SharedAmongstTranslations]
#[EmptyOnTranslate]
class ClassConflictEntity implements TranslatableInterface
{
    use TranslatableTrait;
}
