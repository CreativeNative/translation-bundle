<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Model;

interface TranslatableInterface
{
    /**
     * Returns entity's locale (en/de/...)
     */
    public function getLocale(): string|null;

    /**
     * Sets entity's locale (en/de/...)
     */
    public function setLocale(string|null $locale = null): self;

    /**
     * Returns entity's Translation UUID
     */
    public function getTuuid(): string|null;

    /**
     * Returns translations ids per locale
     *
     * @return array<string, string>
     */
    public function getTranslations(): array;
}
