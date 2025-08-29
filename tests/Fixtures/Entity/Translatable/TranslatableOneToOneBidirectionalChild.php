<?php

namespace TMI\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
#[ORM\Table]
final class TranslatableOneToOneBidirectionalChild implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\OneToOne(
        targetEntity: TranslatableOneToOneBidirectionalParent::class,
        inversedBy: 'simpleChild',
        cascade: ['persist']
    )]
    #[ORM\JoinColumn(nullable: true)]
    private ?TranslatableOneToOneBidirectionalParent $simpleParent = null;

    #[ORM\OneToOne(
        targetEntity: TranslatableOneToOneBidirectionalParent::class,
        inversedBy: 'sharedChild',
        cascade: ['persist']
    )]
    #[ORM\JoinColumn(nullable: true)]
    private ?TranslatableOneToOneBidirectionalParent $sharedParent = null;

    #[ORM\OneToOne(
        targetEntity: TranslatableOneToOneBidirectionalParent::class,
        inversedBy: 'emptyChild',
        cascade: ['persist']
    )]
    #[ORM\JoinColumn(nullable: true)]
    private ?TranslatableOneToOneBidirectionalParent $emptyParent = null;


    public function getId(): int|null
    {
        return $this->id;
    }

    public function getSimpleParent(): TranslatableOneToOneBidirectionalParent|null
    {
        return $this->simpleParent;
    }

    public function setSimpleParent(?TranslatableOneToOneBidirectionalParent $simpleParent): self
    {
        $this->simpleParent = $simpleParent;

        return $this;
    }

    public function getSharedParent(): TranslatableOneToOneBidirectionalParent|null
    {
        return $this->sharedParent;
    }

    public function setSharedParent(?TranslatableOneToOneBidirectionalParent $sharedParent): self
    {
        $this->sharedParent = $sharedParent;

        return $this;
    }

    public function getEmptyParent(): TranslatableOneToOneBidirectionalParent|null
    {
        return $this->emptyParent;
    }

    public function setEmptyParent(?TranslatableOneToOneBidirectionalParent $emptyParent): self
    {
        $this->emptyParent = $emptyParent;

        return $this;
    }
}
