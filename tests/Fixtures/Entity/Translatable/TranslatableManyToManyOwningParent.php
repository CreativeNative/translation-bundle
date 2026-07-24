<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Mirror of TranslatableManyToManyBidirectionalParent with the association direction
 * flipped: here the TRANSLATED entity owns the relation (inversedBy + join table) and the
 * related entity is the inverse side.
 */
#[ORM\Entity]
class TranslatableManyToManyOwningParent implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    /** @var Collection<int, TranslatableManyToManyOwningChild> */
    #[ORM\ManyToMany(
        targetEntity: TranslatableManyToManyOwningChild::class,
        inversedBy: 'owningParents',
        cascade: ['persist'],
    )]
    #[ORM\JoinTable(name: 'owning_parent_child')]
    private Collection $owningChildren;

    public function __construct()
    {
        $this->owningChildren = new ArrayCollection();
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    /**
     * @return Collection<int, TranslatableManyToManyOwningChild>
     */
    public function getOwningChildren(): Collection
    {
        return $this->owningChildren;
    }

    public function addOwningChild(TranslatableManyToManyOwningChild $child): self
    {
        if (!$this->owningChildren->contains($child)) {
            $this->owningChildren->add($child);
            $child->addOwningParent($this);
        }

        return $this;
    }
}
