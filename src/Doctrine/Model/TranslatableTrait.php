<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;

trait TranslatableTrait
{
    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    #[SharedAmongstTranslations]
    private ?string $tuuid = null;

    #[ORM\Column(type: Types::STRING, length: 7, nullable: true)]
    private ?string $locale = null;

    #[ORM\Column(type: Types::JSON)]
    private array $translations = [];

    final public function generateTuuid(): void
    {
        if ($this->tuuid === null) {
            $this->tuuid = Uuid::uuid4()->toString();
        }
    }

    final public function setLocale(?string $locale = null): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Returns entity's locale.
     */
    final public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Set the Translation UUID
     */
    final public function setTuuid(?string $tuuid): self
    {
        $this->tuuid = $tuuid;

        return $this;
    }

    /**
     * Returns entity's Translation UUID.
     */
    final public function getTuuid(): ?string
    {
        return $this->tuuid;
    }

    /**
     * @param array<string, mixed> $translations
     */
    final public function setTranslations(array $translations): self
    {
        $this->translations = $translations;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    final public function getTranslations(): array
    {
        return $this->translations;
    }

    final public function setTranslation(string $locale, array $translation): self
    {
        $this->translations[$locale] = $translation;

        return $this;
    }

    /**
     * @param string $locale
     * @return array|null
     */
    final public function getTranslation(string $locale): ?array
    {
        return $this->translations[$locale] ?? null;
    }
}