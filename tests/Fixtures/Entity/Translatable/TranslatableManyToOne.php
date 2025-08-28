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
final class TranslatableManyToOne implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Scalar::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?Scalar $simple = null;

    /**
     * @SharedAmongstTranslations()
     */
    #[ORM\ManyToOne(targetEntity: Scalar::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?Scalar $shared = null;

    /**
     * @EmptyOnTranslate()
     */
    #[ORM\ManyToOne(targetEntity: Scalar::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?Scalar $empty = null;


    public function getId(): int|null
    {
        return $this->id;
    }


    public function getSimple(): Scalar|null
    {
        return $this->simple;
    }


    public function setSimple(?Scalar $simple = null): self
    {
        $this->simple = $simple;

        return $this;
    }

    public function getShared(): Scalar|null
    {
        return $this->shared;
    }

    public function setShared(?Scalar $shared = null): self
    {
        $this->shared = $shared;

        return $this;
    }

    public function getEmpty(): Scalar|null
    {
        return $this->empty;
    }

    public function setEmpty(?Scalar $empty = null): self
    {
        $this->empty = $empty;

        return $this;
    }

}
