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
class TranslatableOneToManyBidirectionalParent implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    /** @var Collection<int, TranslatableManyToOneBidirectionalChild> */
    #[ORM\OneToMany(targetEntity: TranslatableManyToOneBidirectionalChild::class, mappedBy: 'parentSimple', cascade: ['persist'])]
    private Collection $simpleChildren;

    /** @var Collection<int, TranslatableManyToOneBidirectionalChild> */
    #[SharedAmongstTranslations]
    #[ORM\OneToMany(targetEntity: TranslatableManyToOneBidirectionalChild::class, mappedBy: 'parentShared', cascade: ['persist'])]
    private Collection $sharedChildren;

    /** @var Collection<int, TranslatableManyToOneBidirectionalChild> */
    #[EmptyOnTranslate]
    #[ORM\OneToMany(targetEntity: TranslatableManyToOneBidirectionalChild::class, mappedBy: 'parentEmpty', cascade: ['persist'])]
    private Collection $emptyChildren;

    /** @var Collection<int, NonTranslatableManyToOneBidirectionalChild> */
    #[ORM\OneToMany(targetEntity: NonTranslatableManyToOneBidirectionalChild::class, mappedBy: 'parent', cascade: ['persist'])]
    private Collection $nonTranslatableChildren;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $title = null;

    public function __construct()
    {
        $this->simpleChildren          = new ArrayCollection();
        $this->sharedChildren          = new ArrayCollection();
        $this->emptyChildren           = new ArrayCollection();
        $this->nonTranslatableChildren = new ArrayCollection();
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setId(int|null $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return Collection<int, TranslatableManyToOneBidirectionalChild>
     */
    public function getSimpleChildren(): Collection
    {
        return $this->simpleChildren;
    }

    /** @param Collection<int, TranslatableManyToOneBidirectionalChild> $simpleChildren */
    public function setSimpleChildren(Collection $simpleChildren): self
    {
        $this->simpleChildren = $simpleChildren;

        return $this;
    }

    /**
     * @return Collection<int, TranslatableManyToOneBidirectionalChild>
     */
    public function getSharedChildren(): Collection
    {
        return $this->sharedChildren;
    }

    /** @param Collection<int, TranslatableManyToOneBidirectionalChild> $sharedChildren */
    public function setSharedChildren(Collection $sharedChildren): self
    {
        $this->sharedChildren = $sharedChildren;

        return $this;
    }

    /**
     * @return Collection<int, TranslatableManyToOneBidirectionalChild>
     */
    public function getEmptyChildren(): Collection
    {
        return $this->emptyChildren;
    }

    /** @param Collection<int, TranslatableManyToOneBidirectionalChild> $emptyChildren */
    public function setEmptyChildren(Collection $emptyChildren): self
    {
        $this->emptyChildren = $emptyChildren;

        return $this;
    }

    /**
     * @return Collection<int, NonTranslatableManyToOneBidirectionalChild>
     */
    public function getNonTranslatableChildren(): Collection
    {
        return $this->nonTranslatableChildren;
    }

    /** @param Collection<int, NonTranslatableManyToOneBidirectionalChild> $nonTranslatableChildren */
    public function setNonTranslatableChildren(Collection $nonTranslatableChildren): self
    {
        $this->nonTranslatableChildren = $nonTranslatableChildren;

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
