<?php

declare(strict_types=1);

namespace TMI\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;
use TMI\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use TMI\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;

#[ORM\Entity]
final class TranslatableManyToManyUnidirectionalChild implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $name = null;

    #[SharedAmongstTranslations]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $sharedName = null;

    #[EmptyOnTranslate]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $emptyName = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSharedName(): ?string
    {
        return $this->sharedName;
    }

    public function setSharedName(?string $sharedName): self
    {
        $this->sharedName = $sharedName;
        return $this;
    }

    public function getEmptyName(): ?string
    {
        return $this->emptyName;
    }

    public function setEmptyName(?string $emptyName): self
    {
        $this->emptyName = $emptyName;
        return $this;
    }
}
