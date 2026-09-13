<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Exception;

/**
 * `#[SharedAmongstTranslations]` and `#[EmptyOnTranslate]` both at class level on one
 * embeddable: class-level attributes are the default for every inner property, and a
 * class cannot default to both.
 */
final class ClassLevelAttributeConflictException extends \LogicException
{
    public static function forClass(string $class): self
    {
        return new self(\sprintf(
            'Class-level attribute conflict on %s: #[SharedAmongstTranslations] and #[EmptyOnTranslate] are both placed on the class, '
            .'but a class-level attribute is the default for every property and a class cannot default to being copied AND cleared. '
            .'Solution: keep one class-level attribute and override single properties with the other.',
            $class,
        ));
    }
}
