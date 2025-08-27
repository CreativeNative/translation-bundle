<?php

namespace TMI\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
#[ORM\Table]
class TranslatableOneToManyBidirectionalParent implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    /**
     * A Child has one Parent.
     *
     * @var ArrayCollection
     */
    #[ORM\OneToMany(targetEntity: \TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalChild::class, cascade: ['persist'], mappedBy: 'parent')]
    protected $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return \Doctrine\Common\Collections\Collection<int, \TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalChild>
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param Collection $children
     *
     * @return self
     */
    public function setChildren(?Collection $children = null): self
    {
        $this->children = $children;

        foreach ($children as $child) {
            $child->setParent($this);
        }

        return $this;
    }

}
