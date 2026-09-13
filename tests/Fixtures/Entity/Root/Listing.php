<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Root;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootTrait;

/**
 * A SINGLE_TABLE translation root (5.1): one row per logical object, NOT translatable,
 * owning the tuuid every {@see Estate} row copies. The abstract root declares the
 * unique tuuid column once for both leaves ({@see ListingA}, {@see ListingB}) -- the
 * application's `Listing` shape.
 */
#[ORM\Entity]
#[ORM\Table(name: 'root_listing')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string')]
#[ORM\DiscriminatorMap(['a' => ListingA::class, 'b' => ListingB::class])]
abstract class Listing implements TranslationRootInterface
{
    use TranslationRootTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $family;

    public function __construct(string $family = 'default')
    {
        $this->family = $family;
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getFamily(): string
    {
        return $this->family;
    }
}
