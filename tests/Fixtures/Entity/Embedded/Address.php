<?php

namespace  TMI\TranslationBundle\Fixtures\Entity\Embedded;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class Address
{
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $street = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $country = null;

    /**
     * @return mixed
     */
    public function getStreet()
    {
        return $this->street;
    }

    /**
     * @param mixed $street
     *
     * @return $this
     */
    public function setStreet($street = null)
    {
        $this->street = $street;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPostalCode()
    {
        return $this->postalCode;
    }

    /**
     * @param mixed $postalCode
     *
     * @return $this
     */
    public function setPostalCode($postalCode = null)
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @param mixed $city
     *
     * @return $this
     */
    public function setCity($city = null)
    {
        $this->city = $city;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @param mixed $country
     *
     * @return $this
     */
    public function setCountry($country = null)
    {
        $this->country = $country;

        return $this;
    }
}
