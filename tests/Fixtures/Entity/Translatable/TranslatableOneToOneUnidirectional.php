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
class TranslatableOneToOneUnidirectional implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    /**
     * Scalar value.
     *
     * @var Scalar
     */
    #[ORM\OneToOne(targetEntity: \TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    protected ?\TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar $simple = null;

    /**
     * Scalar value.
     *
     * @var Scalar
     * @SharedAmongstTranslations()
     */
    #[ORM\OneToOne(targetEntity: \TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    protected ?\TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar $shared = null;

    /**
     * Scalar value.
     *
     * @var Scalar
     * @EmptyOnTranslate()
     */
    #[ORM\OneToOne(targetEntity: \TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    protected ?\TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar $empty = null;

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
