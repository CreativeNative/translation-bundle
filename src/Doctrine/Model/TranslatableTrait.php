<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait TranslatableTrait
{
    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    private string|null $tuuid = null;

    #[ORM\Column(type: Types::STRING, length: 7, nullable: true)]
    private string|null $locale = null;

    #[ORM\Column(type: Types::JSON)]
    private array $translations = [];


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
    final public function &getTranslations(): array
    {
        return $this->translations;
    }
}
