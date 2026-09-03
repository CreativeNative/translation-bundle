<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Root of a SINGLE_TABLE inheritance hierarchy: every concrete subclass's
 * rows live in this one physical table, distinguished only by the
 * discriminator column.
 *
 * Regression fixture for the WP5 fix: TranslatableEntityLocator, the
 * doctor/sync-shared commands and LocaleCompletenessResolver must all treat
 * this hierarchy as ONE entity to walk (querying the root already returns
 * every concrete subclass's rows), yet still discover fields declared only on
 * a concrete subclass — {@see StiBook::$isbn} and {@see StiBook::$author} are
 * invisible to a reflection walk of this class alone.
 */
#[ORM\Entity]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'kind', type: Types::STRING)]
#[ORM\DiscriminatorMap(['book' => StiBook::class, 'toy' => StiToy::class])]
abstract class StiRoot implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $name = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setName(string|null $name = null): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string|null
    {
        return $this->name;
    }
}
