<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;

/**
 * Declares its own #[SharedAmongstTranslations] column ($isbn) and its own
 * plain translatable column ($author) — fields no reflection walk of
 * {@see StiRoot} alone would ever see, since they only exist on this
 * subclass's own metadata.
 */
#[ORM\Entity]
class StiBook extends StiRoot
{
    #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $isbn = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $author = null;

    public function setIsbn(string|null $isbn = null): self
    {
        $this->isbn = $isbn;

        return $this;
    }

    public function getIsbn(): string|null
    {
        return $this->isbn;
    }

    public function setAuthor(string|null $author = null): self
    {
        $this->author = $author;

        return $this;
    }

    public function getAuthor(): string|null
    {
        return $this->author;
    }
}
