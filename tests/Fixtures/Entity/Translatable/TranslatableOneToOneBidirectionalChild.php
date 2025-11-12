<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
final class TranslatableOneToOneBidirectionalChild implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

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
