<?php

namespace TMI\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
#[ORM\Table]
class TranslatableOneToManyBidirectionalChild implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    /**
     * Many Children have one Parent.
     */
    #[ORM\ManyToOne(targetEntity: \TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalParent::class, inversedBy: 'children', cascade: ['persist'])]
    #[ORM\JoinColumn(referencedColumnName: 'id')]
    private ?\TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalParent $parent = null;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param mixed $parent
     *
     * @return self
     */
    public function setParent($parent = null): self
    {
        $this->parent = $parent;

        return $this;
    }

}
