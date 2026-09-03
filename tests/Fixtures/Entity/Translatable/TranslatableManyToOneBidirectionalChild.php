<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
class TranslatableManyToOneBidirectionalChild implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $title = null;

    #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $shared = null;

    #[EmptyOnTranslate]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $empty = null;

    #[ORM\ManyToOne(targetEntity: TranslatableOneToManyBidirectionalParent::class, inversedBy: 'simpleChildren')]
    #[ORM\JoinColumn(nullable: true)]
    private TranslatableOneToManyBidirectionalParent|null $parentSimple = null;

    #[ORM\ManyToOne(targetEntity: TranslatableOneToManyBidirectionalParent::class, inversedBy: 'sharedChildren')]
    #[ORM\JoinColumn(nullable: true)]
    private TranslatableOneToManyBidirectionalParent|null $parentShared = null;

    #[ORM\ManyToOne(targetEntity: TranslatableOneToManyBidirectionalParent::class, inversedBy: 'emptyChildren')]
    #[ORM\JoinColumn(nullable: true)]
    private TranslatableOneToManyBidirectionalParent|null $parentEmpty = null;

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

    public function getShared(): string|null
    {
        return $this->shared;
    }

    public function setShared(string|null $shared): self
    {
        $this->shared = $shared;

        return $this;
    }

    public function getEmpty(): string|null
    {
        return $this->empty;
    }

    public function setEmpty(string|null $empty): self
    {
        $this->empty = $empty;

        return $this;
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
