<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\ValueObject;

/**
 * Per-locale translation status of a Tuuid group, as resolved by
 * {@see \Tmi\TranslationBundle\Translation\LocaleCompletenessResolver}.
 */
enum TranslationStatus: string
{
    /** No variant row exists for the locale. */
    case Missing = 'missing';

    /**
     * A variant exists, but at least one translatable property that is filled
     * on the baseline variant is empty on it.
     */
    case Incomplete = 'incomplete';

    /**
     * A variant exists and carries a value for every translatable property
     * that is filled on the baseline variant.
     */
    case Complete = 'complete';
}
