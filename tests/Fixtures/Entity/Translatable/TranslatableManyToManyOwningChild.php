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
 * Inverse side of TranslatableManyToManyOwningParent::$owningChildren.
 */
#[ORM\Entity]
class TranslatableManyToManyOwningChild implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    /** @var Collection<int, TranslatableManyToManyOwningParent> */
    #[ORM\ManyToMany(
        targetEntity: TranslatableManyToManyOwningParent::class,
        mappedBy: 'owningChildren',
        cascade: ['persist'],
    )]
    private Collection $owningParents;

    public function __construct()
    {
        $this->owningParents = new ArrayCollection();
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    /**
     * @return Collection<int, TranslatableManyToManyOwningParent>
     */
    public function getOwningParents(): Collection
    {
        return $this->owningParents;
    }

    public function addOwningParent(TranslatableManyToManyOwningParent $parent): self
    {
        if (!$this->owningParents->contains($parent)) {
            $this->owningParents->add($parent);
        }

        return $this;
    }
}
