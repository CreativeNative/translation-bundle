<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Bughunt;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
#[ORM\Table(name: 'bughunt_rooted_row')]
class RootedRow implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $title = null;

    #[ORM\ManyToOne(targetEntity: RowRoot::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'root_id', nullable: false)]
    private RowRoot $root;

    public function __construct(RowRoot $root)
    {
        $this->root = $root;
        $this->setTuuid($root->getTuuid());
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getRoot(): RowRoot
    {
        return $this->root;
    }

    public function getTitle(): string|null
    {
        return $this->title;
    }

    public function setTitle(string|null $t): self
    {
        $this->title = $t;

        return $this;
    }
}
