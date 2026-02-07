<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Scalar;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
class Scalar implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $title = null;

    #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $shared = null;

    #[EmptyOnTranslate]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $empty = null;

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

    public function setShared(string|null $shared = null): self
    {
        $this->shared = $shared;

        return $this;
    }

    public function getShared(): string|null
    {
        return $this->shared;
    }

    public function setEmpty(string|null $empty = null): self
    {
        $this->empty = $empty;

        return $this;
    }

    public function getEmpty(): string|null
    {
        return $this->empty;
    }
}
