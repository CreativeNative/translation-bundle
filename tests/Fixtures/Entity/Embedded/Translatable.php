<?php

namespace  Tmi\TranslationBundle\Fixtures\Entity\Embedded;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
final class Translatable implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Embedded(class: Address::class)]
    private Address|null $address;

    #[EmptyOnTranslate]
    #[ORM\Embedded(class: Address::class)]
    private Address|null $emptyAddress;

    public function __construct()
    {
        $this->address      = new Address();
        $this->emptyAddress = new Address();
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getAddress(): Address|null
    {
        return $this->address;
    }

    public function setAddress(Address|null $address = null): self
    {
        $this->address = $address;

        return $this;
    }

    public function getEmptyAddress(): Address|null
    {
        return $this->emptyAddress;
    }

    public function setEmptyAddress(Address|null $emptyAddress = null): self
    {
        $this->emptyAddress = $emptyAddress;

        return $this;
    }
}
