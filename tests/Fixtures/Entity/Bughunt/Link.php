<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Bughunt;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * A self-referential bidirectional OneToOne (`$next` owns, `$previous` is the inverse):
 * the chain shape BidirectionalOneToOneHandler must translate without pointing a
 * clone at itself.
 */
#[ORM\Entity]
#[ORM\Table(name: 'bughunt_link')]
class Link implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING)]
    private string $name = '';

    #[ORM\OneToOne(targetEntity: self::class, inversedBy: 'previous')]
    private Link|null $next = null;

    #[ORM\OneToOne(targetEntity: self::class, mappedBy: 'next')]
    private Link|null $previous = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNext(): Link|null
    {
        return $this->next;
    }

    public function setNext(Link|null $next): self
    {
        $this->next = $next;

        if (null !== $next) {
            $next->previous = $this;
        }

        return $this;
    }

    public function getPrevious(): Link|null
    {
        return $this->previous;
    }

    public function setPrevious(Link|null $previous): self
    {
        $this->previous = $previous;

        return $this;
    }
}
