<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Model;

interface TranslatableInterface
{

    public function getLocale(): string|null;

    public function setLocale(string|null $locale = null): self;

    public function getTuuid(): string|null;

    /**
     * Returns translations ids per locale
     *
     * @return array<string, string>
     */
    public function getTranslations(): array;
}
