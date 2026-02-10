<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
class CanNotBeNull implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int|null $id = null;

    #[EmptyOnTranslate]
    #[ORM\Column(type: Types::STRING, nullable: false)]
    private string $emptyNotNullable;

    #[EmptyOnTranslate]
    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $count = 5;

    #[EmptyOnTranslate]
    #[ORM\Column(type: Types::FLOAT, nullable: false)]
    private float $price = 9.99;

    #[EmptyOnTranslate]
    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $active = true;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setEmptyNotNullable(string|null $emptyNotNullable = null): self
    {
        $this->emptyNotNullable = $emptyNotNullable ?? '';

        return $this;
    }

    public function getEmptyNotNullable(): string
    {
        return $this->emptyNotNullable;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }
}
