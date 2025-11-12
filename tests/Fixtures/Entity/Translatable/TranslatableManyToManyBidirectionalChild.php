<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
final class TranslatableManyToManyBidirectionalChild implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    /**
     * @var ArrayCollection
     */
    #[ORM\ManyToMany(
        targetEntity: TranslatableManyToManyBidirectionalParent::class,
        inversedBy: 'simpleChildren',
        cascade: ['persist'],
    )]
    #[ORM\JoinTable(name: 'parent_child')]
    private Collection $simpleParents;

    /**
     * @var ArrayCollection
     */
    #[ORM\ManyToMany(
        targetEntity: TranslatableManyToManyBidirectionalParent::class,
        inversedBy: 'sharedChildren',
        cascade: ['persist'],
    )]
    #[ORM\JoinTable(name: 'parent_shared_child')]
    private Collection $sharedParents;

    /**
     * @var ArrayCollection
     */
    #[ORM\ManyToMany(
        targetEntity: TranslatableManyToManyBidirectionalParent::class,
        inversedBy: 'emptyChildren',
        cascade: ['persist'],
    )]
    #[ORM\JoinTable(name: 'parent_empty_child')]
    private Collection $emptyParents;

    public function __construct()
    {
        $this->simpleParents = new ArrayCollection();
        $this->sharedParents = new ArrayCollection();
        $this->emptyParents  = new ArrayCollection();
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    /**
     * @return Collection<int, TranslatableManyToManyBidirectionalParent>
     */
    public function getSimpleParents(): Collection
    {
        return $this->simpleParents;
    }

    public function addSimpleParent(TranslatableManyToManyBidirectionalParent $parent): self
    {
        if (!$this->simpleParents->contains($parent)) {
            $this->simpleParents->add($parent);
            $parent->addSimpleChild($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, TranslatableManyToManyBidirectionalParent>
     */
    public function getSharedParents(): Collection
    {
        return $this->sharedParents;
    }

    public function addSharedParents(TranslatableManyToManyBidirectionalParent $parent): self
    {
        if (!$this->sharedParents->contains($parent)) {
            $this->sharedParents->add($parent);
            $parent->addSimpleChild($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, TranslatableManyToManyBidirectionalParent>
     */
    public function getEmptyParents(): Collection
    {
        return $this->emptyParents;
    }

    public function addEmptyParent(TranslatableManyToManyBidirectionalParent $parent): self
    {
        if (!$this->emptyParents->contains($parent)) {
            $this->emptyParents->add($parent);
            $parent->addSimpleChild($this);
        }

        return $this;
    }
}
