<?php

namespace  TMI\TranslationBundle\Fixtures\Entity\Embedded;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class Address
{
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $street = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $country = null;

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street = null): self
    {
        $this->street = $street;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode = null): self
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city = null): self
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country = null): self
    {
        $this->country = $country;

        return $this;
    }
}
