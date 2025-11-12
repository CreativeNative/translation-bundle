<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class NonTranslatableManyToOneBidirectionalChild
{
    #[ORM\Id]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\ManyToOne(targetEntity: TranslatableOneToManyBidirectionalParent::class, inversedBy: 'nonTranslatableChildren')]
    #[ORM\JoinColumn(nullable: true)]
    private TranslatableOneToManyBidirectionalParent|null $parent = null;

    private string|null $title = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setId(int|null $id): NonTranslatableManyToOneBidirectionalChild
    {
        $this->id = $id;

        return $this;
    }

    public function getParent(): TranslatableOneToManyBidirectionalParent|null
    {
        return $this->parent;
    }

    public function setParent(TranslatableOneToManyBidirectionalParent|null $parent): NonTranslatableManyToOneBidirectionalChild
    {
        $this->parent = $parent;

        return $this;
    }

    public function getTitle(): string|null
    {
        return $this->title;
    }

    public function setTitle(string|null $title): NonTranslatableManyToOneBidirectionalChild
    {
        $this->title = $title;

        return $this;
    }
}
