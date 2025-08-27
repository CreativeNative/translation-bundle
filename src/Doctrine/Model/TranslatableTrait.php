<?php

namespace TMI\TranslationBundle\Doctrine\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * @author Arthur Guigand <aguigand@tmi.fr>
 */
trait TranslatableTrait
{
    /**
     * @var string|null
     */
    #[ORM\Column(type: Types::GUID, length: 36)]
    protected ?string $tuuid = null;

    /**
     * @var string|null
     */
    #[ORM\Column(type: Types::STRING, length: 7)]
    protected ?string $locale = null;

    /**
     * @var array
     */
    #[ORM\Column(type: Types::JSON)]
    protected array $translations = [];

    /**
     * Set the locale
     */
    public function setLocale(?string $locale = null): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Returns entity's locale.
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Set the Translation UUID
     */
    public function setTuuid(?string $tuuid): self
    {
        $this->tuuid = $tuuid;

        return $this;
    }

    /**
     * Returns entity's Translation UUID.
     */
    public function getTuuid(): ?string
    {
        return $this->tuuid;
    }

    public function setTranslations(array $translations): self
    {
        $this->translations = $translations;

        return $this;
    }

    public function getTranslations(): array
    {
        return $this->translations;
    }
}
