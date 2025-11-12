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
final class TranslatableOneToOneBidirectionalParent implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\OneToOne(
        targetEntity: TranslatableOneToOneBidirectionalChild::class,
        mappedBy: 'simpleParent',
        cascade: ['persist'],
    )]
    private TranslatableOneToOneBidirectionalChild|null $simpleChild = null;

    #[SharedAmongstTranslations]
    #[ORM\OneToOne(
        targetEntity: TranslatableOneToOneBidirectionalChild::class,
        mappedBy: 'sharedParent',
        cascade: ['persist'],
    )]
    private TranslatableOneToOneBidirectionalChild|null $sharedChild = null;

    #[EmptyOnTranslate]
    #[ORM\OneToOne(
        targetEntity: TranslatableOneToOneBidirectionalChild::class,
        mappedBy: 'emptyParent',
        cascade: ['persist'],
    )]
    private TranslatableOneToOneBidirectionalChild|null $emptyChild = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getSimpleChild(): TranslatableOneToOneBidirectionalChild|null
    {
        return $this->simpleChild;
    }

    public function setSimpleChild(TranslatableOneToOneBidirectionalChild|null $simpleChild): self
    {
        $this->simpleChild = $simpleChild;

        return $this;
    }

    public function getSharedChild(): TranslatableOneToOneBidirectionalChild|null
    {
        return $this->sharedChild;
    }

    public function setSharedChild(TranslatableOneToOneBidirectionalChild|null $sharedChild): self
    {
        $this->sharedChild = $sharedChild;

        return $this;
    }

    public function getEmptyChild(): TranslatableOneToOneBidirectionalChild|null
    {
        return $this->emptyChild;
    }

    public function setEmptyChild(TranslatableOneToOneBidirectionalChild|null $emptyChild): self
    {
        $this->emptyChild = $emptyChild;

        return $this;
    }
}
