<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Seeding;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\Translatable;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Entity whose variants are seeded empty (copySource: false) — the shape that
 * needs the POST_TRANSLATE seeding hook to mint placeholder values.
 */
#[ORM\Entity]
#[Translatable(copySource: false)]
class EmptySeeded implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $title = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $slug = null;

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

    public function setSlug(string|null $slug = null): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSlug(): string|null
    {
        return $this->slug;
    }
}
