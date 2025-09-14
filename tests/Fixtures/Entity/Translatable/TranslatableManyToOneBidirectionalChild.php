<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
final class TranslatableManyToOneBidirectionalChild implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\ManyToOne(targetEntity: TranslatableOneToManyBidirectionalParent::class, cascade: ['persist'], inversedBy: 'simpleChildren')]
    #[ORM\JoinColumn(nullable: true)]
    private TranslatableOneToManyBidirectionalParent|null $parentSimple = null;

    #[ORM\ManyToOne(targetEntity: TranslatableOneToManyBidirectionalParent::class, cascade: ['persist'], inversedBy: 'sharedChildren')]
    #[ORM\JoinColumn(nullable: true)]
    private TranslatableOneToManyBidirectionalParent|null $parentShared = null;

    #[ORM\ManyToOne(targetEntity: TranslatableOneToManyBidirectionalParent::class, cascade: ['persist'], inversedBy: 'emptyChildren')]
    #[ORM\JoinColumn(nullable: true)]
    private TranslatableOneToManyBidirectionalParent|null $parentEmpty = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getParentSimple(): TranslatableOneToManyBidirectionalParent|null
    {
        return $this->parentSimple;
    }

    public function setParentSimple(TranslatableOneToManyBidirectionalParent|null $parentSimple): self
    {
        $this->parentSimple = $parentSimple;
        return $this;
    }

    public function getParentShared(): TranslatableOneToManyBidirectionalParent|null
    {
        return $this->parentShared;
    }

    public function setParentShared(TranslatableOneToManyBidirectionalParent|null $parentShared): self
    {
        $this->parentShared = $parentShared;
        return $this;
    }

    public function getParentEmpty(): TranslatableOneToManyBidirectionalParent|null
    {
        return $this->parentEmpty;
    }

    public function setParentEmpty(TranslatableOneToManyBidirectionalParent|null $parentEmpty): self
    {
        $this->parentEmpty = $parentEmpty;
        return $this;
    }
}
