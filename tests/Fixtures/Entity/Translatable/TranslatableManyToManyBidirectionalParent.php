<?php

namespace TMI\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use TMI\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
class TranslatableManyToManyBidirectionalParent implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    /**
     * @var ArrayCollection
     */
    #[ORM\ManyToMany(targetEntity: \TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalChild::class, mappedBy: 'simpleParents')]
    private $simpleChildren;

    /**
     * @var ArrayCollection
     *
     * @EmptyOnTranslate()
     */
    #[ORM\ManyToMany(targetEntity: \TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalChild::class, mappedBy: 'emptyParents')]
    #[ORM\JoinTable(name: 'empty_translatablemanytomanybidirectionalchild_translatablemanytomanybidirectionalparent')]
    private $emptyChildren;


    /**
     * @var ArrayCollection
     *
     * @SharedAmongstTranslations()
     */
    #[ORM\ManyToMany(targetEntity: \TMI\TranslationBundle\Fixtures\Entity\Translatable\ManyToManyBidirectionalChild::class, mappedBy: 'sharedParents', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'shared_manytomanybidirectionalchild_translatablemanytomanybidirectionalparent')]
    private $sharedChildren;

    public function __construct()
    {
        $this->simpleChildren = new ArrayCollection();
        $this->emptyChildren  = new ArrayCollection();
        $this->sharedChildren = new ArrayCollection();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return \Doctrine\Common\Collections\Collection<int, \TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalChild>
     */
    public function getSimpleChildren()
    {
        return $this->simpleChildren;
    }

    public function addSimpleChild(TranslatableManyToManyBidirectionalChild $child)
    {
        $child->addSimpleParent($this);

        $this->simpleChildren[] = $child;

        return $this;
    }

    /**
     * @return \Doctrine\Common\Collections\Collection<int, \TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalChild>
     */
    public function getEmptyChildren()
    {
        return $this->emptyChildren;
    }

    public function addEmptyChild(TranslatableManyToManyBidirectionalChild $child)
    {
        $child->addEmptyParent($this);

        $this->emptyChildren[] = $child;

        return $this;
    }

    /**
     * @return \Doctrine\Common\Collections\Collection<int, \TMI\TranslationBundle\Fixtures\Entity\Translatable\ManyToManyBidirectionalChild>
     */
    public function getSharedChildren()
    {
        return $this->sharedChildren;
    }

    /**
     * @param Collection $sharedChildren
     *
     * @return TranslatableManyToManyBidirectionalParent
     */
    public function setSharedChildren(Collection $sharedChildren)
    {
        $this->sharedChildren = $sharedChildren;

        return $this;
    }

    public function addSharedChild(ManyToManyBidirectionalChild $child)
    {
        $child->addSharedParent($this);

        $this->sharedChildren[] = $child;

        return $this;
    }
}
