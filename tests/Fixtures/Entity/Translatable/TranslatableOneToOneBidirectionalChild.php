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
class TranslatableOneToOneBidirectionalChild implements TranslatableInterface
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

    #[ORM\OneToOne(
        targetEntity: TranslatableOneToOneBidirectionalParent::class,
        inversedBy: 'simpleChild',
        cascade: ['persist'],
    )]
    #[ORM\JoinColumn(nullable: true)]
    private TranslatableOneToOneBidirectionalParent|null $simpleParent = null;

    #[ORM\OneToOne(
        targetEntity: TranslatableOneToOneBidirectionalParent::class,
        inversedBy: 'sharedChild',
        cascade: ['persist'],
    )]
    #[ORM\JoinColumn(nullable: true)]
    private TranslatableOneToOneBidirectionalParent|null $sharedParent = null;

    #[ORM\OneToOne(
        targetEntity: TranslatableOneToOneBidirectionalParent::class,
        inversedBy: 'emptyChild',
        cascade: ['persist'],
    )]
    #[ORM\JoinColumn(nullable: true)]
    private TranslatableOneToOneBidirectionalParent|null $emptyParent = null;

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

    public function getSimpleParent(): TranslatableOneToOneBidirectionalParent|null
    {
        return $this->simpleParent;
    }

    public function setSimpleParent(TranslatableOneToOneBidirectionalParent|null $simpleParent): self
    {
        $this->simpleParent = $simpleParent;

        return $this;
    }

    public function getSharedParent(): TranslatableOneToOneBidirectionalParent|null
    {
        return $this->sharedParent;
    }

    public function setSharedParent(TranslatableOneToOneBidirectionalParent|null $sharedParent): self
    {
        $this->sharedParent = $sharedParent;

        return $this;
    }

    public function getEmptyParent(): TranslatableOneToOneBidirectionalParent|null
    {
        return $this->emptyParent;
    }

    public function setEmptyParent(TranslatableOneToOneBidirectionalParent|null $emptyParent): self
    {
        $this->emptyParent = $emptyParent;

        return $this;
    }
}
