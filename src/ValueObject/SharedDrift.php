<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\ValueObject;

/**
 * One drifted #[SharedAmongstTranslations] value: a sibling locale variant
 * whose property differs from the row the back-fill treats as canonical.
 *
 * Produced by {@see \Tmi\TranslationBundle\Doctrine\SharedDriftScanner::scan()},
 * one instance per (sibling row, property path). Carries locations, never the
 * values themselves, so a report can be logged or mailed without leaking
 * content. `$readonly` marks a property that differs but cannot be written
 * after hydration -- `tmi:translation:sync-shared` reports such rows and
 * leaves them for a manual or database-level correction.
 */
final readonly class SharedDrift
{
    /**
     * @param class-string $entityClass  the sibling's concrete entity class
     * @param string       $propertyPath `field`, `embedded.field`, or `embedded` for a whole shared embeddable
     * @param string       $sourceLocale locale of the row the value was compared against
     * @param string       $locale       locale of the drifted sibling row
     */
    public function __construct(
        private string $entityClass,
        private string $tuuid,
        private string $propertyPath,
        private string $sourceLocale,
        private string $locale,
        private bool $readonly,
    ) {
    }

    /**
     * @return class-string
     */
    public function entityClass(): string
    {
        return $this->entityClass;
    }

    public function tuuid(): string
    {
        return $this->tuuid;
    }

    public function propertyPath(): string
    {
        return $this->propertyPath;
    }

    public function sourceLocale(): string
    {
        return $this->sourceLocale;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }
}
