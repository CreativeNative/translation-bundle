<?php

namespace TMI\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
#[ORM\Table]
final class TranslatableOneToManyBidirectionalParent implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * A Child has one Parent.
     *
     * @var ArrayCollection
     */
    #[ORM\OneToMany(
        targetEntity: TranslatableOneToManyBidirectionalChild::class,
        mappedBy: 'parent',
        cascade: ['persist']
    )]
    private iterable $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function setChildren(?Collection $children = null): self
    {
        $this->children = $children;

        foreach ($children as $child) {
            $child->setParent($this);
        }

        return $this;
    }
}
