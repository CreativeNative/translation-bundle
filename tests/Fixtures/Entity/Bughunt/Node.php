<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Bughunt;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * A self-referential bidirectional ManyToOne tree (`$parent` inversedBy `children`).
 * On this shape the direct form and the back-reference form of the ManyToOne handler
 * look identical to the mapping; before 5.2 the translated root received its own child
 * as parent (backlog #54).
 */
#[ORM\Entity]
#[ORM\Table(name: 'bughunt_node')]
class Node implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    private Node|null $parent = null;

    /** @var Collection<int, Node> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $children;

    public function __construct(string $name)
    {
        $this->name     = $name;
        $this->children = new ArrayCollection();
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParent(): Node|null
    {
        return $this->parent;
    }

    public function setParent(Node|null $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /** @return Collection<int, Node> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(Node $child): self
    {
        $this->children->add($child);
        $child->setParent($this);

        return $this;
    }
}
