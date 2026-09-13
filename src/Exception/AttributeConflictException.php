<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Exception;

/**
 * `#[SharedAmongstTranslations]` and `#[EmptyOnTranslate]` on the same property: a
 * value cannot both be copied from the source and cleared when a translation is
 * created. Raised by `AttributeValidationPass` at container compile and by
 * `AttributeHelper::validateProperty()` at translate time.
 */
final class AttributeConflictException extends \LogicException
{
    public static function forSharedAndEmpty(string $class, string $property): self
    {
        return new self(\sprintf(
            'Attribute conflict on %s::$%s: #[SharedAmongstTranslations] and #[EmptyOnTranslate] are mutually exclusive -- '
            .'a value cannot both be copied from the source and cleared when a translation is created. '
            .'Solution: keep #[SharedAmongstTranslations] for a value every locale shares, or #[EmptyOnTranslate] for one each locale fills in itself.',
            $class,
            $property,
        ));
    }
}
