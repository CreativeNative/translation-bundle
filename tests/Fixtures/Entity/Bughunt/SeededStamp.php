<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Bughunt;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Attribute\Translatable;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Value-object properties under `copy_source: false` (the per-entity switch): a nullable
 * DateTimeImmutable without an attribute is seeded null on a new variant, a shared one
 * is copied, and a NON-nullable one falls back to the copy (the "non-nullable object
 * safety fallback" in EntityTranslator::runHandlers()). Before 5.2 all three were copied.
 */
#[ORM\Entity]
#[ORM\Table(name: 'bughunt_seeded_stamp')]
#[Translatable(copySource: false)]
class SeededStamp implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int|null $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private \DateTimeImmutable|null $publishedAt = null;

    #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private \DateTimeImmutable|null $approvedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('2020-01-01 00:00:00');
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getPublishedAt(): \DateTimeImmutable|null
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(\DateTimeImmutable|null $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getApprovedAt(): \DateTimeImmutable|null
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(\DateTimeImmutable|null $approvedAt): self
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
