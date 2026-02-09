<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Model;

use Tmi\TranslationBundle\ValueObject\Tuuid;

interface TranslatableInterface
{
    public function generateTuuid(): void;

    public function getTuuid(): Tuuid;

    public function getLocale(): string|null;

    public function setLocale(string|null $locale = null): self;

    /**
     * Returns translations ids per locale.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getTranslations(): array;
}
