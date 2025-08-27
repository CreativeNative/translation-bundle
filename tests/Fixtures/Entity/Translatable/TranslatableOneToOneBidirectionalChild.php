<?php

namespace TMI\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
#[ORM\Table]
class TranslatableOneToOneBidirectionalChild implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    #[ORM\OneToOne(targetEntity: \TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalParent::class, inversedBy: 'simpleChild')]
    #[ORM\JoinColumn(nullable: true)]
    private ?\TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalParent $simpleParent = null;

    #[ORM\OneToOne(targetEntity: \TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalParent::class, inversedBy: 'sharedChild')]
    #[ORM\JoinColumn(nullable: true)]
    private ?\TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalParent $sharedParent = null;

    #[ORM\OneToOne(targetEntity: \TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalParent::class, inversedBy: 'emptyChild')]
    #[ORM\JoinColumn(nullable: true)]
    private ?\TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalParent $emptyParent = null;

    /**
     * @return mixed
     */
    public function getSimpleParent()
    {
        return $this->simpleParent;
    }

    /**
     * @param mixed $simpleParent
     *
     * @return $this
     */
    public function setSimpleParent($simpleParent)
    {
        $this->simpleParent = $simpleParent;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSharedParent()
    {
        return $this->sharedParent;
    }

    /**
     * @param mixed $sharedParent
     *
     * @return $this
     */
    public function setSharedParent($sharedParent)
    {
        $this->sharedParent = $sharedParent;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getEmptyParent()
    {
        return $this->emptyParent;
    }

    /**
     * @param mixed $emptyParent
     *
     * @return $this
     */
    public function setEmptyParent($emptyParent)
    {
        $this->emptyParent = $emptyParent;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

}
