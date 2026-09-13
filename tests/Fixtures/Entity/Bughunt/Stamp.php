<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Bughunt;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Enum\Priority;

/**
 * Value-object properties under #[EmptyOnTranslate]: a DateTimeImmutable and an enum
 * next to a plain string. Before 5.2 only the string was emptied -- no handler claimed
 * the two objects, so the attribute cascade never ran for them (backlog #53).
 */
#[ORM\Entity]
#[ORM\Table(name: 'bughunt_stamp')]
class Stamp implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int|null $id = null;

    #[EmptyOnTranslate]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private \DateTimeImmutable|null $publishedAt = null;

    #[EmptyOnTranslate]
    #[ORM\Column(type: Types::STRING, nullable: true, enumType: Priority::class)]
    private Priority|null $priority = null;

    #[EmptyOnTranslate]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $note = null;

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

    public function getPriority(): Priority|null
    {
        return $this->priority;
    }

    public function setPriority(Priority|null $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getNote(): string|null
    {
        return $this->note;
    }

    public function setNote(string|null $note): self
    {
        $this->note = $note;

        return $this;
    }
}
