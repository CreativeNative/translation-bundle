<?php

namespace TMI\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * @ORM\Entity
 */
class TranslatableManyToManyBidirectionalChild implements TranslatableInterface
{
    use TranslatableTrait;

    /**
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue()
     */
    protected $id;

    /**
     * @var ArrayCollection
     *
     * @ORM\ManyToMany(
     *     targetEntity="AppTestBundle\Entity\Translatable\TranslatableManyToManyBidirectionalParent",
     *     cascade={"persist"},
     *     inversedBy="simpleChildren"
     * )
     */
    protected $simpleParents;

    /**
     * @var ArrayCollection
     *
     * @ORM\ManyToMany(
     *     targetEntity="AppTestBundle\Entity\Translatable\TranslatableManyToManyBidirectionalParent",
     *     cascade={"persist"},
     *     inversedBy="emptyChildren"
     * )
     * @ORM\JoinTable(name="empty_translatablemanytomanybidirectionalchild_translatablemanytomanybidirectionalparent")
     */
    protected $emptyParents;

    public function __construct()
    {
        $this->simpleParents = new ArrayCollection();
        $this->emptyParents  = new ArrayCollection();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return ArrayCollection
     */
    public function getSimpleParents()
    {
        return $this->simpleParents;
    }

    public function addSimpleParent(TranslatableManyToManyBidirectionalParent $parent)
    {
        $this->simpleParents[] = $parent;

        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getEmptyParents()
    {
        return $this->emptyParents;
    }

    public function addEmptyParent(TranslatableManyToManyBidirectionalParent $parent)
    {
        $this->emptyParents[] = $parent;

        return $this;
    }
}
