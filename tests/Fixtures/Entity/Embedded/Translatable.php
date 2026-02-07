<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Embedded;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Fixtures\Entity\Embedded\AddressWithEmptyAndSharedProperty;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
class Translatable implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    // Normal Address: no special attributes, will be cloned on translation
    #[ORM\Embedded(class: Address::class)]
    private Address|AddressWithEmptyAndSharedProperty|null $address;

    // Address with #[EmptyOnTranslate]: will be emptied on translation
    #[EmptyOnTranslate]
    #[ORM\Embedded(class: Address::class)]
    private Address|null $emptyAddress;

    // Address with #[SharedAmongstTranslations]: shared across translations
    #[SharedAmongstTranslations]
    #[ORM\Embedded(class: Address::class)]
    private Address|null $sharedAddress;

    public function __construct()
    {
        $this->address       = new Address();
        $this->emptyAddress  = new Address();
        $this->sharedAddress = new Address();
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getAddress(): Address|AddressWithEmptyAndSharedProperty|null
    {
        return $this->address;
    }

    public function setAddress(Address|AddressWithEmptyAndSharedProperty|null $address = null): self
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

    public function getSharedAddress(): Address|null
    {
        return $this->sharedAddress;
    }

    public function setSharedAddress(Address|null $sharedAddress = null): self
    {
        $this->sharedAddress = $sharedAddress;

        return $this;
    }
}
