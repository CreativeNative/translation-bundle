<?php

namespace TMI\TranslationBundle\Doctrine\Model;

interface TranslatableInterface
{
    /**
     * Returns entity's locale (en/de/...)
     */
    public function getLocale(): ?string;

    /**
     * Returns entity's Translation UUID
     */
    public function getTuuid(): ?string;

    /**
     * Returns translations ids per locale
     */
    public function getTranslations(): array;
}
