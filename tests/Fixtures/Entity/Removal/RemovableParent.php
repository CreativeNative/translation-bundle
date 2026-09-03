<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Removal;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Minimal translatable entity with an ORM-cascaded child collection.
 *
 * Exists to prove that {@see \Tmi\TranslationBundle\Doctrine\TranslatableRemover}
 * removes each locale variant through `EntityManager::remove()` — so ORM
 * cascade / `orphanRemoval` fires once per variant — rather than through a
 * bulk DQL DELETE, which would bypass it entirely.
 */
#[ORM\Entity]
class RemovableParent implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $title = null;

    /** @var Collection<int, RemovableChild> */
    #[ORM\OneToMany(
        mappedBy: 'parent',
        targetEntity: RemovableChild::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setTitle(string|null $title = null): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): string|null
    {
        return $this->title;
    }

    /**
     * @return Collection<int, RemovableChild>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(RemovableChild $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }
}
