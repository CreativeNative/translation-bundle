<?php

namespace Tmi\TranslationBundle\Doctrine\Model;

interface TranslatableInterface
{
    /**
     * Returns entity's locale (en/de/...)
     */
    public function getLocale(): ?string;

    /**
     * Sets entity's locale (en/de/...)
     */
    public function setLocale(?string $locale = null): self;

    /**
     * Returns entity's Translation UUID
     */
    public function getTuuid(): ?string;

    /**
     * Returns translations ids per locale
     *
     * @return array<string, string>
     */
    public function getTranslations(): array;
}
