<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Embedded;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class Address
{
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, nullable: true)]
    private string|null $street = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, nullable: true)]
    private string|null $postalCode = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, nullable: true)]
    private string|null $city = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, nullable: true)]
    private string|null $country = null;

    public function getStreet(): string|null
    {
        return $this->street;
    }

    public function setStreet(string|null $street = null): self
    {
        $this->street = $street;

        return $this;
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
