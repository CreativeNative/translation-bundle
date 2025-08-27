<?php

namespace TMI\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use TMI\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use TMI\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
#[ORM\Table]
class TranslatableManyToOne implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Scalar::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    protected ?Scalar $simple = null;

    /**
     * @SharedAmongstTranslations()
     */
    #[ORM\ManyToOne(targetEntity: Scalar::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    protected ?Scalar $shared = null;

    /**
     * @EmptyOnTranslate()
     */
    #[ORM\ManyToOne(targetEntity: Scalar::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    protected ?Scalar $empty = null;

    /**
     * @return Scalar
     */
    public function getSimple()
    {
        return $this->simple;
    }

    /**
     * @param Scalar $simple
     *
     * @return $this
     */
    public function setSimple(?Scalar $simple = null)
    {
        $this->simple = $simple;

        return $this;
    }

    /**
     * @return Scalar
     */
    public function getShared()
    {
        return $this->shared;
    }

    /**
     * @param Scalar $shared
     *
     * @return $this
     */
    public function setShared(?Scalar $shared = null)
    {
        $this->shared = $shared;

        return $this;
    }

    /**
     * @return Scalar
     */
    public function getEmpty()
    {
        return $this->empty;
    }

    /**
     * @param Scalar $empty
     *
     * @return $this
     */
    public function setEmpty(?Scalar $empty = null)
    {
        $this->empty = $empty;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
}
