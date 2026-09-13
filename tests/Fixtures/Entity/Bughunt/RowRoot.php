<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Bughunt;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootTrait;

/**
 * A translation root with the inverse side of its rows' root reference -- the SPEC § 4
 * reference shape (`ManyToOne(inversedBy: 'translations')` on the row).
 */
#[ORM\Entity]
#[ORM\Table(name: 'bughunt_row_root')]
class RowRoot implements TranslationRootInterface
{
    use TranslationRootTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    /** @var Collection<int, RootedRow> */
    #[ORM\OneToMany(targetEntity: RootedRow::class, mappedBy: 'root')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public static function mint(): self
    {
        $root = new self();
        $root->mintTuuid();

        return $root;
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    /** @return Collection<int, RootedRow> */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }
}
