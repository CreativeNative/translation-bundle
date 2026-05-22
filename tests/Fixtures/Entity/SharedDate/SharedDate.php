<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\SharedDate;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Fixture with an object-valued #[SharedAmongstTranslations] column
 * (a publish date shared across all locale variants).
 */
#[ORM\Entity]
class SharedDate implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $title = null;

    #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private \DateTimeImmutable|null $publishedAt = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setTitle(string|null $title = null): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): string|null
    {
        return $this->title;
    }

    public function setPublishedAt(\DateTimeImmutable|null $publishedAt = null): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getPublishedAt(): \DateTimeImmutable|null
    {
        return $this->publishedAt;
    }
}
