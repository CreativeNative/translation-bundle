<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Embedded;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;

#[ORM\Embeddable]
#[SharedAmongstTranslations]
final class SharedClassEmbeddable
{
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $sharedByDefault = null;

    #[EmptyOnTranslate]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $overriddenToEmpty = null;

    public function getSharedByDefault(): string|null
    {
        return $this->sharedByDefault;
    }

    public function setSharedByDefault(string|null $sharedByDefault = null): self
    {
        $this->sharedByDefault = $sharedByDefault;

        return $this;
    }

    public function getOverriddenToEmpty(): string|null
    {
        return $this->overriddenToEmpty;
    }

    public function setOverriddenToEmpty(string|null $overriddenToEmpty = null): self
    {
        $this->overriddenToEmpty = $overriddenToEmpty;

        return $this;
    }
}
