<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Exception;

/**
 * Thrown when a translatable entity is persisted in a non-default locale
 * without a shared Tuuid — i.e. linked to no other locale variant.
 *
 * This is almost always a mistake: a legitimately new entity is created in
 * the default locale, while translations must be produced via
 * {@see \Tmi\TranslationBundle\Translation\EntityTranslator::translate()}
 * so they inherit the canonical Tuuid.
 */
final class OrphanTranslationException extends \LogicException
{
    public static function forEntity(string $class, string $locale): self
    {
        return new self(sprintf(
            'Translatable "%s" is being persisted in non-default locale "%s" without a shared Tuuid. '
            .'This creates a standalone entity linked to no other locale variant. '
            .'Use EntityTranslator::translate() to create linked translations, '
            .'or set "tmi_translation.strict_orphan_check: false" if this is intentional.',
            $class,
            $locale,
        ));
    }
}
