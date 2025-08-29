<?php

namespace TMI\TranslationBundle\Doctrine\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait TranslatableTrait
{
    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    private ?string $tuuid = null;

    #[ORM\Column(type: Types::STRING, length: 7, nullable: true)]
    private ?string $locale = null;

    #[ORM\Column(type: Types::JSON)]
    private array $translations = [];


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

    final public function setTranslations(array $translations): self
    {
        $this->translations = $translations;

        return $this;
    }

    final public function getTranslations(): array
    {
        return $this->translations;
    }
}
