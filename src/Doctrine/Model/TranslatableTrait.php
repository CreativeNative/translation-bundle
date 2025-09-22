<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;

trait TranslatableTrait
{
    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    #[SharedAmongstTranslations]
    private string|null $tuuid = null;

    #[ORM\Column(type: Types::STRING, length: 7, nullable: true)]
    private string|null $locale = null;

    #[ORM\Column(type: Types::JSON)]
    private array $translations = [];

    final public function generateTuuid(): void
    {
        if ($this->tuuid === null) {
            $this->tuuid = Uuid::uuid4()->toString();
        }
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
     * Set the Translation UUID
     */
    final public function setTuuid(string|null $tuuid): self
    {
        if ($tuuid !== null) {
            try {
                // This will throw if the string is not a valid UUID
                Uuid::fromString($tuuid);
            } catch (InvalidUuidStringException $e) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid UUID provided for tuuid: "%s"',
                    $tuuid
                ));
            }
        }

        $this->tuuid = $tuuid;

        return $this;
    }

    /**
     * Returns entity's Translation UUID.
     */
    final public function getTuuid(): string|null
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

    /**
     * @param array<string, mixed> $translation
     */
    final public function setTranslation(string $locale, array $translation): self
    {
        $this->translations[$locale] = $translation;

        return $this;
    }

    /**
     */
    final public function getTranslation(string $locale): array|null
    {
        return $this->translations[$locale] ?? null;
    }
}
