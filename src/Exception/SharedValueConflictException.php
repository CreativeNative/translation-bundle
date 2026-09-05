<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Exception;

/**
 * Thrown by the opt-in flush-time propagation
 * ({@see \Tmi\TranslationBundle\Doctrine\EventListener\SharedValuePropagationListener})
 * when two locale variants of one Tuuid are scheduled for update in the same
 * flush with DIFFERENT new values for the same #[SharedAmongstTranslations]
 * property.
 *
 * Never last-wins: silently picking one locale would recreate the very
 * defect the propagation exists to remove (one logical record that disagrees
 * with itself across locales). The flush is rolled back untouched; the
 * application edits a shared value on one variant per flush.
 */
final class SharedValueConflictException extends \RuntimeException
{
    public static function forProperty(
        string $class,
        string $tuuid,
        string $path,
        string $localeA,
        mixed $valueA,
        string $localeB,
        mixed $valueB,
    ): self {
        return new self(sprintf(
            'Shared property %s::$%s of tuuid %s is being flushed with two different values at once: '
            .'%s on locale "%s" and %s on locale "%s". A #[SharedAmongstTranslations] value must be edited '
            .'on one locale variant per flush -- with propagate_shared_on_flush enabled the bundle refuses '
            .'to pick a winner.',
            $class,
            $path,
            $tuuid,
            self::describe($valueA),
            $localeA,
            self::describe($valueB),
            $localeB,
        ));
    }

    /**
     * A short, safe rendering of a property value for the message -- never
     * serialize(), which could recurse into an entity graph.
     */
    private static function describe(mixed $value): string
    {
        return match (true) {
            null === $value                      => 'null',
            is_bool($value)                      => $value ? 'true' : 'false',
            is_scalar($value)                    => sprintf('"%s"', (string) $value),
            $value instanceof \UnitEnum          => $value::class.'::'.$value->name,
            $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
            $value instanceof \Stringable        => sprintf('"%s"', (string) $value),
            is_object($value)                    => $value::class,
            default                              => gettype($value),
        };
    }
}
