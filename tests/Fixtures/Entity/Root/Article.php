<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Root;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * The translation rows of an {@see ArticleRoot}, PHASE 2 of the rollout (5.1): the root
 * reference is NON-nullable, so the compile-time constructor rule is on -- the
 * constructor requires the root and copies its tuuid, never mints. No #[TranslationRoot]
 * marker: the reference is a root reference by its type alone.
 */
#[ORM\Entity]
#[ORM\Table(name: 'root_article')]
class Article implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $title = null;

    #[ORM\ManyToOne(targetEntity: ArticleRoot::class)]
    #[ORM\JoinColumn(name: 'root_id', nullable: false)]
    private ArticleRoot $root;

    public function __construct(ArticleRoot $root)
    {
        $this->root = $root;
        $this->setTuuid($root->getTuuid());
    }

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

    public function getRoot(): ArticleRoot
    {
        return $this->root;
    }
}
