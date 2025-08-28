<?php

namespace TMI\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class ManyToManyBidirectionalChild
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToMany(
        targetEntity: TranslatableManyToManyBidirectionalParent::class,
        inversedBy: 'sharedChildren',
        cascade: ['persist'])]
    #[ORM\JoinTable(name: 'shared_parent')]
    private iterable $sharedParents;

    public function __construct()
    {
        $this->sharedParents = new ArrayCollection();
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getSharedParents(): Collection
    {
        return $this->sharedParents;
    }

    public function addSharedParent(TranslatableManyToManyBidirectionalParent $parent): self
    {
        $this->sharedParents[] = $parent;

        return $this;
    }
}
