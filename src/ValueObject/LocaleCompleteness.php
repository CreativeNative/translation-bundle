<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\ValueObject;

/**
 * Per-locale completeness of one Tuuid group: for every enabled locale,
 * whether a variant exists and whether its translatable content is complete
 * relative to the baseline variant.
 *
 * Produced by {@see \Tmi\TranslationBundle\Translation\LocaleCompletenessResolver}.
 */
final readonly class LocaleCompleteness
{
    /**
     * @param array<string, TranslationStatus> $statuses one entry per enabled locale
     */
    public function __construct(
        private Tuuid $tuuid,
        private array $statuses,
        private string|null $baselineLocale,
    ) {
    }

    public function tuuid(): Tuuid
    {
        return $this->tuuid;
    }

    /**
     * Locale of the variant the comparison ran against: the default locale
     * when that variant exists, otherwise the first existing variant. Null
     * when no variant exists at all.
     */
    public function baselineLocale(): string|null
    {
        return $this->baselineLocale;
    }

    /**
     * @return array<string, TranslationStatus> locale => status, one entry per enabled locale
     */
    public function statuses(): array
    {
        return $this->statuses;
    }

    public function statusOf(string $locale): TranslationStatus
    {
        return $this->statuses[$locale] ?? TranslationStatus::Missing;
    }

    public function hasVariant(string $locale): bool
    {
        return TranslationStatus::Missing !== $this->statusOf($locale);
    }

    /**
     * @return list<string>
     */
    public function missingLocales(): array
    {
        return $this->localesWithStatus(TranslationStatus::Missing);
    }

    /**
     * @return list<string>
     */
    public function incompleteLocales(): array
    {
        return $this->localesWithStatus(TranslationStatus::Incomplete);
    }

    /**
     * @return list<string>
     */
    public function completeLocales(): array
    {
        return $this->localesWithStatus(TranslationStatus::Complete);
    }

    /**
     * Whether every enabled locale has a complete variant.
     */
    public function isFullyTranslated(): bool
    {
        return [] === $this->missingLocales() && [] === $this->incompleteLocales();
    }

    /**
     * @return list<string>
     */
    private function localesWithStatus(TranslationStatus $status): array
    {
        return array_keys(array_filter(
            $this->statuses,
            static fn (TranslationStatus $candidate): bool => $candidate === $status,
        ));
    }
}
