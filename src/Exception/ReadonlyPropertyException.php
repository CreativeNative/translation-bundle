<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Exception;

/**
 * A PHP `readonly` property that translate() would have to write. Readonly means
 * "set once": an already-hydrated readonly property cannot take a second value, so
 * the two cases below are both refused -- one at compile time, one when a clone is
 * resolved.
 */
final class ReadonlyPropertyException extends \LogicException
{
    /** `#[EmptyOnTranslate]` on a readonly property: the attribute clears the value on every new translation. */
    public static function forEmptyOnTranslate(string $class, string $property): self
    {
        return new self(\sprintf(
            'Invalid #[EmptyOnTranslate] on readonly property %s::$%s: a readonly property can only be set once, but the attribute clears the value when a translation is created. '
            .'Solution: drop the readonly modifier, or remove #[EmptyOnTranslate].',
            $class,
            $property,
        ));
    }

    /** translate() resolved a different value for a readonly property and cannot store it on the clone. */
    public static function forWriteDuringTranslate(string $class, string $property): self
    {
        return new self(\sprintf(
            'Property %s::$%s is readonly and cannot be reassigned while translating. '
            .'Solution: mark it #[SharedAmongstTranslations] so every locale keeps the same value, or drop the readonly modifier.',
            $class,
            $property,
        ));
    }
}
