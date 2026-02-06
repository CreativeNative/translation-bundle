<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Embedded;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;

#[ORM\Embeddable]
#[SharedAmongstTranslations]
#[EmptyOnTranslate]
final class ConflictClassEmbeddable
{
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $conflicted = null;

    public function getConflicted(): string|null
    {
        return $this->conflicted;
    }

    public function setConflicted(string|null $conflicted = null): self
    {
        $this->conflicted = $conflicted;

        return $this;
    }
}
