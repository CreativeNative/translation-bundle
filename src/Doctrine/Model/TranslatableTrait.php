<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\ValueObject\Tuuid;

trait TranslatableTrait
{
    #[ORM\Column(type: 'tuuid', length: 36, nullable: true)]
    #[SharedAmongstTranslations]
    private Tuuid|null $tuuid = null;

    #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
    private string|null $locale = null;

    /** @var array<string, array<string, mixed>> */
    #[ORM\Column(type: Types::JSON)]
    private array $translations = [];

    final public function generateTuuid(): void
    {
        if (null === $this->tuuid) {
            $this->tuuid = Tuuid::generate();
        }
    }

    /**
     * Set the Translation UUID.
     */
    final public function setTuuid(Tuuid|null $tuuid): self
    {
        // Initial assignment always allowed (including null for cloning/tests)
        if (null === $this->tuuid) {
            $this->tuuid = $tuuid;

            return $this;
        }

        // Doctrine rehydration of the same value:
        // - Only applies if both sides are Tuuid instances
        // - Compare via Tuuid::equals()
        if ($tuuid instanceof Tuuid && $this->tuuid->equals($tuuid)) {
            return $this;
        }

        // Everything else would reassign a previously set Tuuid
        throw new \LogicException('Tuuid is immutable and cannot be reassigned.');
    }

    /**
     * Returns entity's Translation UUID.
     */
    final public function getTuuid(): Tuuid
    {
        if (null === $this->tuuid) {
            $this->tuuid = Tuuid::generate();
        }

        return $this->tuuid;
    }

    final public function setLocale(string|null $locale = null): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Returns entity's locale.
     */
    final public function getLocale(): string|null
    {
        return $this->locale;
    }

    /**
     * @param array<string, array<string, mixed>> $translations
     */
    final public function setTranslations(array $translations): self
    {
        $this->translations = $translations;

        return $this;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    final public function getTranslations(): array
    {
        return $this->translations;
    }

    /**
     * @param array<string, mixed> $translation
     */
    final public function setTranslation(string $locale, array $translation): self
    {
        $this->translations[$locale] = $translation;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    final public function getTranslation(string $locale): array|null
    {
        return $this->translations[$locale] ?? null;
    }
}
