<?php

declare(strict_types=1);

namespace Fixtures\Entity\Embedded;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;

#[ORM\Embeddable]
final class AddressWithEmptyAndSharedProperty
{
    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[EmptyOnTranslate]
    private string|null $street = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[EmptyOnTranslate]
    private string|null $noSetter;

    // Normal property: will be cloned during translation
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $postalCode = null;

    // Normal property: will be cloned during translation
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $city = null;

    #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $country = null;

    /**
     * @param string|null $noSetter Default value. May be set to null via reflection (e.g. EmptyOnTranslate handler).
     */
    public function __construct(string|null $noSetter = 'no Setter')
    {
        $this->noSetter = $noSetter;
    }

    public function getStreet(): string|null
    {
        return $this->street;
    }

    public function setStreet(string|null $street = null): self
    {
        $this->street = $street;

        return $this;
    }

    public function getNoSetter(): string|null
    {
        return $this->noSetter;
    }

    public function getPostalCode(): string|null
    {
        return $this->postalCode;
    }

    public function setPostalCode(string|null $postalCode = null): self
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCity(): string|null
    {
        return $this->city;
    }

    public function setCity(string|null $city = null): self
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): string|null
    {
        return $this->country;
    }

    public function setCountry(string|null $country = null): self
    {
        $this->country = $country;

        return $this;
    }
}
