<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
final class CanNotBeNull implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int|null $id = null;

    #[EmptyOnTranslate]
    #[ORM\Column(type: Types::STRING, nullable: false)]
    private string|null $emptyNotNullable = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setEmptyNotNullable(string|null $emptyNotNullable = null): CanNotBeNull
    {
        $this->emptyNotNullable = $emptyNotNullable;

        return $this;
    }

    public function getEmptyNotNullable(): string|null
    {
        return $this->emptyNotNullable;
    }
}
