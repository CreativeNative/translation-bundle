<?php

namespace Tmi\TranslationBundle\Fixtures\Entity;

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
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private ?int $id = null;

    #[EmptyOnTranslate]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING)]
    private ?string $emptyNotNullable = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setEmptyNotNullable(?string $emptyNotNullable = null): CanNotBeNull
    {
        $this->emptyNotNullable = $emptyNotNullable;

        return $this;
    }

    public function getEmptyNotNullable(): string|null
    {
        return $this->emptyNotNullable;
    }
}
