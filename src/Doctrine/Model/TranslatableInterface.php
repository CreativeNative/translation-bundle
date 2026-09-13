<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Model;

use Tmi\TranslationBundle\ValueObject\Tuuid;

interface TranslatableInterface
{
    /**
     * The two columns every translatable row carries for the bundle itself, never
     * shared, never checked for completeness, never unique on their own.
     *
     * @var list<string>
     */
    public const array SYSTEM_PROPERTIES = ['tuuid', 'locale'];

    /** The width of the `locale` column; setLocale() refuses anything longer. */
    public const int LOCALE_LENGTH = 16;

    public function generateTuuid(): void;

    /**
     * Returns whether a Tuuid has already been assigned, without auto-generating one.
     */
    public function hasTuuid(): bool;

    public function getTuuid(): Tuuid;

    public function getLocale(): string|null;

    public function setLocale(string|null $locale = null): self;
}
