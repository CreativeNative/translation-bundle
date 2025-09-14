<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
final class TranslatableManyToManyBidirectionalParent implements TranslatableInterface
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
        targetEntity: TranslatableManyToManyBidirectionalChild::class,
        mappedBy: 'simpleParents',
        cascade: ['persist', 'remove']
    )]
    private Collection $simpleChildren;

    #[SharedAmongstTranslations]
    #[ORM\ManyToMany(
        targetEntity: TranslatableManyToManyBidirectionalChild::class,
        mappedBy: 'sharedParents',
        cascade: ['persist', 'remove']
    )]
    private Collection $sharedChildren;

    #[EmptyOnTranslate]
    #[ORM\ManyToMany(
        targetEntity: TranslatableManyToManyBidirectionalChild::class,
        mappedBy: 'emptyParents',
        cascade: ['persist', 'remove']
    )]
    private Collection $emptyChildren;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $title = null;

    public function __construct()
    {
        $this->simpleChildren = new ArrayCollection();
        $this->sharedChildren = new ArrayCollection();
        $this->emptyChildren = new ArrayCollection();
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
        if (!$this->simpleChildren->contains($child)) {
            $this->simpleChildren->add($child);
            $child->addSimpleParent($this);
        }

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
        if (!$this->sharedChildren->contains($child)) {
            $this->sharedChildren->add($child);
            $child->addSharedParents($this);
        }

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
        if (!$this->emptyChildren->contains($child)) {
            $this->emptyChildren->add($child);
            $child->addEmptyParent($this);
        }

        return $this;
    }

    public function setTitle(string|null $title = null): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): string|null
    {
        return $this->title;
    }
}
