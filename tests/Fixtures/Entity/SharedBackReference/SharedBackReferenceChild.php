<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\SharedBackReference;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * A translatable child whose bidirectional ManyToOne back-reference to its
 * translatable parent carries #[SharedAmongstTranslations] -- the shape an
 * application's `ItineraryDay::$itinerary` has. Reached through the parent's
 * OneToMany, the property is the child's own FK back to the parent and resolves to
 * the parent's clone (5.1); reached directly it is still refused, as it always was.
 */
#[ORM\Entity]
class SharedBackReferenceChild implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $title = null;

    #[SharedAmongstTranslations]
    #[ORM\ManyToOne(targetEntity: SharedBackReferenceParent::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true)]
    private SharedBackReferenceParent|null $parent = null;

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

    public function getParent(): SharedBackReferenceParent|null
    {
        return $this->parent;
    }

    public function setParent(SharedBackReferenceParent|null $parent): self
    {
        $this->parent = $parent;

        return $this;
    }
}
