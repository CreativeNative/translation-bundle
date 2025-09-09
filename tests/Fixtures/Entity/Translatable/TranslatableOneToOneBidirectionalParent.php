<?php

namespace Tmi\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
final class TranslatableOneToOneBidirectionalParent implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\OneToOne(
        targetEntity: TranslatableOneToOneBidirectionalChild::class,
        mappedBy: 'simpleParent',
        cascade: ['persist']
    )]
    private ?TranslatableOneToOneBidirectionalChild $simpleChild = null;

    #[SharedAmongstTranslations]
    #[ORM\OneToOne(
        targetEntity: TranslatableOneToOneBidirectionalChild::class,
        mappedBy: 'sharedParent',
        cascade: ['persist']
    )]
    private ?TranslatableOneToOneBidirectionalChild $sharedChild = null;

    #[EmptyOnTranslate]
    #[ORM\OneToOne(
        targetEntity: TranslatableOneToOneBidirectionalChild::class,
        mappedBy: 'emptyParent',
        cascade: ['persist']
    )]
    private ?TranslatableOneToOneBidirectionalChild $emptyChild = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getSimpleChild(): TranslatableOneToOneBidirectionalChild|null
    {
        return $this->simpleChild;
    }

    public function setSimpleChild(?TranslatableOneToOneBidirectionalChild $simpleChild): self
    {
        $this->simpleChild = $simpleChild;

        return $this;
    }

    public function getSharedChild(): TranslatableOneToOneBidirectionalChild|null
    {
        return $this->sharedChild;
    }

    public function setSharedChild(?TranslatableOneToOneBidirectionalChild $sharedChild): self
    {
        $this->sharedChild = $sharedChild;

        return $this;
    }

    public function getEmptyChild(): TranslatableOneToOneBidirectionalChild|null
    {
        return $this->emptyChild;
    }

    public function setEmptyChild(?TranslatableOneToOneBidirectionalChild $emptyChild): self
    {
        $this->emptyChild = $emptyChild;

        return $this;
    }
}
