<?php

namespace TMI\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use TMI\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
final class TranslatableManyToManyBidirectionalParent implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * @var ArrayCollection
     */
    #[ORM\ManyToMany(
        targetEntity: TranslatableManyToManyBidirectionalChild::class,
        mappedBy: 'simpleParents',
        cascade: ['persist', 'remove']
    )]
    private iterable $simpleChildren;

    #[EmptyOnTranslate]
    #[ORM\ManyToMany(
        targetEntity: TranslatableManyToManyBidirectionalChild::class,
        mappedBy: 'emptyParents',
        cascade: ['persist', 'remove']
    )]
    private iterable $emptyChildren;

    #[SharedAmongstTranslations]
    #[ORM\ManyToMany(
        targetEntity: TranslatableManyToManyBidirectionalChild::class,
        mappedBy: 'sharedParents',
        cascade: ['persist', 'remove']
    )]
    private iterable $sharedChildren;

    public function __construct()
    {
        $this->simpleChildren = new ArrayCollection();
        $this->emptyChildren  = new ArrayCollection();
        $this->sharedChildren = new ArrayCollection();
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    /**
     * @return Collection<int, TranslatableManyToManyBidirectionalChild>
     */
    public function getSimpleChildren(): Collection
    {
        return $this->simpleChildren;
    }

    public function addSimpleChild(TranslatableManyToManyBidirectionalChild $child): self
    {
        $child->addSimpleParent($this);

        $this->simpleChildren[] = $child;

        return $this;
    }

    /**
     * @return Collection<int, TranslatableManyToManyBidirectionalChild>
     */
    public function getEmptyChildren(): Collection
    {
        return $this->emptyChildren;
    }

    public function addEmptyChild(TranslatableManyToManyBidirectionalChild $child): self
    {
        $child->addEmptyParent($this);

        $this->emptyChildren[] = $child;

        return $this;
    }

    /**
     * @return Collection<int, TranslatableManyToManyBidirectionalChild>
     */
    public function getSharedChildren(): Collection
    {
        return $this->sharedChildren;
    }

    public function setSharedChildren(Collection $sharedChildren): self
    {
        $this->sharedChildren = $sharedChildren;

        return $this;
    }

    public function addSharedChild(TranslatableManyToManyBidirectionalChild $child): self
    {
        $child->addSharedParents($this);

        $this->sharedChildren[] = $child;

        return $this;
    }
}
