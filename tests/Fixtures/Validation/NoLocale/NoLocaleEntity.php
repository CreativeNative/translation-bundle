<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\NoLocale;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * Test fixture implementing TranslatableInterface without TranslatableTrait.
 * Missing locale property to trigger validation error.
 */
class NoLocaleEntity implements TranslatableInterface
{
    private Tuuid|null $tuuid = null;

    /** @var array<string, array<string, mixed>> */
    private array $translations = [];

    public function generateTuuid(): void
    {
        if (null === $this->tuuid) {
            $this->tuuid = Tuuid::generate();
        }
    }

    public function getTuuid(): Tuuid
    {
        if (null === $this->tuuid) {
            $this->generateTuuid();
        }

        // PHPStan doesn't understand that generateTuuid() guarantees non-null
        assert(null !== $this->tuuid);

        return $this->tuuid;
    }

    public function getLocale(): string|null
    {
        // No locale property, so return null
        return null;
    }

    public function setLocale(string|null $locale = null): self
    {
        // No locale property, so this is a no-op
        return $this;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getTranslations(): array
    {
        return $this->translations;
    }
}
