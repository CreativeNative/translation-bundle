<?php

namespace  TMI\TranslationBundle\Fixtures\Entity\Scalar;

use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use TMI\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
final class Scalar implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, nullable: true)]
    private ?string $title = null;

    #[SharedAmongstTranslations]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, nullable: true)]
    private ?string $shared = null;

    #[EmptyOnTranslate]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, nullable: true)]
    private ?string $empty = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setTitle(?string $title = null): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): string|null
    {
        return $this->title;
    }

    public function setShared(?string $shared = null): self
    {
        $this->shared = $shared;

        return $this;
    }

    public function getShared(): string|null
    {
        return $this->shared;
    }

    public function setEmpty(?string $empty = null): self
    {
        $this->empty = $empty;

        return $this;
    }

    public function getEmpty(): string|null
    {
        return $this->empty;
    }
}
