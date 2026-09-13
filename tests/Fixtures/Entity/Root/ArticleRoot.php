<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Root;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootTrait;

/**
 * A plain (non-STI) translation root for {@see Article}, the PHASE 2 fixture.
 */
#[ORM\Entity]
#[ORM\Table(name: 'root_article_root')]
class ArticleRoot implements TranslationRootInterface
{
    use TranslationRootTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

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
}
