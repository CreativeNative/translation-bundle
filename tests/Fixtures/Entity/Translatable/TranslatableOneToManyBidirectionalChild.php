<?php

namespace Tmi\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
final class TranslatableOneToManyBidirectionalChild implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * Many Children have one Parent.
     */
    #[ORM\ManyToOne(
        targetEntity: TranslatableOneToManyBidirectionalParent::class,
        cascade: ['persist'],
        inversedBy: 'children'
    )]
    #[ORM\JoinColumn(referencedColumnName: 'id')]
    private ?TranslatableOneToManyBidirectionalParent $parent = null;

    public function getId(): int|null
    {
        return $this->id;
    }


    public function getParent(): TranslatableOneToManyBidirectionalParent|null
    {
        return $this->parent;
    }

    public function setParent(?TranslatableOneToManyBidirectionalParent $parent = null): self
    {
        $this->parent = $parent;

        return $this;
    }
}
