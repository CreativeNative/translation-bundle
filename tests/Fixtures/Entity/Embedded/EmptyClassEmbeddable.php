<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Embedded;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;

#[ORM\Embeddable]
#[EmptyOnTranslate]
final class EmptyClassEmbeddable
{
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $emptyByDefault = null;

    #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $overriddenToShared = null;

    public function getEmptyByDefault(): string|null
    {
        return $this->emptyByDefault;
    }

    public function setEmptyByDefault(string|null $emptyByDefault = null): self
    {
        $this->emptyByDefault = $emptyByDefault;

        return $this;
    }

    public function getOverriddenToShared(): string|null
    {
        return $this->overriddenToShared;
    }

    public function setOverriddenToShared(string|null $overriddenToShared = null): self
    {
        $this->overriddenToShared = $overriddenToShared;

        return $this;
    }
}
