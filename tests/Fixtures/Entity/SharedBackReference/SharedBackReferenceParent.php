<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\SharedBackReference;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * The inverse side of the one shape no other fixture has: a translatable parent whose
 * translatable children mark their ManyToOne back-reference to it as
 * #[SharedAmongstTranslations] (see {@see SharedBackReferenceChild}). The collection
 * itself is NOT shared -- the attribute sits on the child's to-one side only.
 */
#[ORM\Entity]
class SharedBackReferenceParent implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $title = null;

    /** @var Collection<int, SharedBackReferenceChild> */
    #[ORM\OneToMany(targetEntity: SharedBackReferenceChild::class, mappedBy: 'parent', cascade: ['persist'])]
    private Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getTitle(): string|null
    {
        return $this->title;
    }

    public function setTitle(string|null $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return Collection<int, SharedBackReferenceChild>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /** @param Collection<int, SharedBackReferenceChild> $children */
    public function setChildren(Collection $children): self
    {
        $this->children = $children;

        return $this;
    }

    public function addChild(SharedBackReferenceChild $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }
}
