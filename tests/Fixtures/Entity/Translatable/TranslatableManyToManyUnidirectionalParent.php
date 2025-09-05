<?php

declare(strict_types=1);

namespace TMI\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use TMI\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
#[ORM\Table(name: 'translatable_many_to_many_unidirectional')]
final class TranslatableManyToManyUnidirectionalParent implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * Simple children collection
     *
     * @var Collection<int, TranslatableManyToManyUnidirectionalChild>
     */
    #[ORM\ManyToMany(targetEntity: TranslatableManyToManyUnidirectionalChild::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'unidirectional_simple_children')]
    private Collection $simpleChildren;

    /**
     * Empty-on-translate collection
     *
     * @var Collection<int, TranslatableManyToManyUnidirectionalChild>
     */
    #[EmptyOnTranslate]
    #[ORM\ManyToMany(targetEntity: TranslatableManyToManyUnidirectionalChild::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'unidirectional_empty_children')]
    private Collection $emptyChildren;

    /**
     * Shared-across-translations collection
     *
     * @var Collection<int, TranslatableManyToManyUnidirectionalChild>
     */
    #[SharedAmongstTranslations]
    #[ORM\ManyToMany(targetEntity: TranslatableManyToManyUnidirectionalChild::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'unidirectional_shared_children')]
    private Collection $sharedChildren;

    public function __construct()
    {
        $this->simpleChildren = new ArrayCollection();
        $this->emptyChildren  = new ArrayCollection();
        $this->sharedChildren = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSimpleChildren(): Collection
    {
        return $this->simpleChildren;
    }

    public function addSimpleChild(TranslatableManyToManyUnidirectionalChild $child): self
    {
        if (!$this->simpleChildren->contains($child)) {
            $this->simpleChildren->add($child);
        }
        return $this;
    }

    public function getEmptyChildren(): Collection
    {
        return $this->emptyChildren;
    }

    public function addEmptyChild(TranslatableManyToManyUnidirectionalChild $child): self
    {
        if (!$this->emptyChildren->contains($child)) {
            $this->emptyChildren->add($child);
        }
        return $this;
    }

    public function getSharedChildren(): Collection
    {
        return $this->sharedChildren;
    }

    public function addSharedChild(TranslatableManyToManyUnidirectionalChild $child): self
    {
        if (!$this->sharedChildren->contains($child)) {
            $this->sharedChildren->add($child);
        }
        return $this;
    }
}
