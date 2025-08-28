<?php

namespace TMI\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
final class TranslatableManyToManyBidirectionalChild implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * @var ArrayCollection
     */
    #[ORM\ManyToMany(
        targetEntity: TranslatableManyToManyBidirectionalParent::class,
        inversedBy: 'simpleChildren',
        cascade: ['persist']
    )]
    #[ORM\JoinTable(name: 'parent_child')]
    private iterable $simpleParents;

    /**
     * @var ArrayCollection
     */
    #[ORM\ManyToMany(
        targetEntity: TranslatableManyToManyBidirectionalParent::class,
        inversedBy: 'emptyChildren',
        cascade: ['persist']
    )]
    #[ORM\JoinTable(name: 'parent_empty_child')]
    private iterable $emptyParents;

    public function __construct()
    {
        $this->simpleParents = new ArrayCollection();
        $this->emptyParents  = new ArrayCollection();
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getSimpleParents(): Collection
    {
        return $this->simpleParents;
    }

    public function addSimpleParent(TranslatableManyToManyBidirectionalParent $parent): self
    {
        $this->simpleParents[] = $parent;

        return $this;
    }

    public function getEmptyParents(): Collection
    {
        return $this->emptyParents;
    }

    public function addEmptyParent(TranslatableManyToManyBidirectionalParent $parent): self
    {
        $this->emptyParents[] = $parent;

        return $this;
    }
}
