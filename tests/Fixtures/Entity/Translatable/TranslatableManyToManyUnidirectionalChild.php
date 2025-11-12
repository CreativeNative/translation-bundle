<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
final class TranslatableManyToManyUnidirectionalChild implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private string|null $name = null;

    #[SharedAmongstTranslations]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private string|null $sharedName = null;

    #[EmptyOnTranslate]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private string|null $emptyName = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getName(): string|null
    {
        return $this->name;
    }

    public function setName(string|null $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSharedName(): string|null
    {
        return $this->sharedName;
    }

    public function setSharedName(string|null $sharedName): self
    {
        $this->sharedName = $sharedName;

        return $this;
    }

    public function getEmptyName(): string|null
    {
        return $this->emptyName;
    }

    public function setEmptyName(string|null $emptyName): self
    {
        $this->emptyName = $emptyName;

        return $this;
    }
}
