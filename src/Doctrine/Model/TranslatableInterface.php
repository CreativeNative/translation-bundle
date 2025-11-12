<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Model;

use Tmi\TranslationBundle\ValueObject\Tuuid;

interface TranslatableInterface
{
    public function getTuuid(): Tuuid|null;

    public function setTuuid(Tuuid|null $tuuid): self;

    public function getLocale(): string|null;

    public function setLocale(string|null $locale = null): self;

    /**
     * Returns translations ids per locale
     *
     * @return array<string, string>
     */
    public function getTranslations(): array;
}
