<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Inverse side of TranslatableOneToOneOwningParent::$owningChild.
 */
#[ORM\Entity]
class TranslatableOneToOneOwningChild implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\OneToOne(
        targetEntity: TranslatableOneToOneOwningParent::class,
        mappedBy: 'owningChild',
        cascade: ['persist'],
    )]
    private TranslatableOneToOneOwningParent|null $owningParent = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getOwningParent(): TranslatableOneToOneOwningParent|null
    {
        return $this->owningParent;
    }

    public function setOwningParent(TranslatableOneToOneOwningParent|null $owningParent): self
    {
        $this->owningParent = $owningParent;

        return $this;
    }
}
