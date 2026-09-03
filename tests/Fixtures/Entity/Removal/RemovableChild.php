<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Removal;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Plain (non-translatable) child of {@see RemovableParent} — deliberately
 * outside the translation machinery, so removing it through the parent's
 * cascade proves ORM cascade behavior, not anything translator-specific.
 */
#[ORM\Entity]
class RemovableChild
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\ManyToOne(targetEntity: RemovableParent::class, inversedBy: 'children')]
    private RemovableParent|null $parent = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $name = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getParent(): RemovableParent|null
    {
        return $this->parent;
    }

    public function setParent(RemovableParent|null $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function setName(string|null $name = null): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string|null
    {
        return $this->name;
    }
}
