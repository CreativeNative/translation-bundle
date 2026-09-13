<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Exception;

/**
 * `#[EmptyOnTranslate]` on a property whose type has no "empty" value.
 *
 * Emptying a nullable property writes null; a non-nullable scalar takes its type's
 * zero value (see TypeDefaultResolver). A non-nullable object type -- a date, an enum,
 * a value object -- has neither, so the attribute could only fail at translate time.
 * Reported at container compile (AttributeValidationPass) and translate time alike,
 * with the same way out the resolver would name.
 */
final class EmptyOnTranslateTypeException extends \LogicException
{
    public static function forNonNullableObject(string $class, string $property, string $type): self
    {
        return new self(\sprintf(
            '%s::$%s carries #[EmptyOnTranslate] but is a non-nullable %s, which has no empty value to translate to. '
            .'Solution: make the property nullable, remove #[EmptyOnTranslate], or use #[SharedAmongstTranslations].',
            $class,
            $property,
            $type,
        ));
    }
}
