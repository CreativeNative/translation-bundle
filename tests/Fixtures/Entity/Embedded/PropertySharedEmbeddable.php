<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Embedded;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;

/**
 * Sharing declared on an inner property only — neither the entity property holding this
 * embeddable nor the class itself carries the attribute.
 */
#[ORM\Embeddable]
final class PropertySharedEmbeddable
{
    #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $reference = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $label = null;

    public function getReference(): string|null
    {
        return $this->reference;
    }

    public function setReference(string|null $reference = null): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getLabel(): string|null
    {
        return $this->label;
    }

    public function setLabel(string|null $label = null): self
    {
        $this->label = $label;

        return $this;
    }
}
