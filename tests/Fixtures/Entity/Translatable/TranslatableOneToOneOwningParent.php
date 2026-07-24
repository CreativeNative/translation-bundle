<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Mirror of TranslatableOneToOneBidirectionalParent with the association direction flipped:
 * here the TRANSLATED entity owns the relation (inversedBy + join column) and the related
 * entity is the inverse side.
 */
#[ORM\Entity]
class TranslatableOneToOneOwningParent implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\OneToOne(
        targetEntity: TranslatableOneToOneOwningChild::class,
        inversedBy: 'owningParent',
        cascade: ['persist'],
    )]
    #[ORM\JoinColumn(nullable: true)]
    private TranslatableOneToOneOwningChild|null $owningChild = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getOwningChild(): TranslatableOneToOneOwningChild|null
    {
        return $this->owningChild;
    }

    public function setOwningChild(TranslatableOneToOneOwningChild|null $owningChild): self
    {
        $this->owningChild = $owningChild;

        return $this;
    }
}
