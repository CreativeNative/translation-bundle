<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
final class TranslatableManyToOne implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\ManyToOne(targetEntity: Scalar::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    private Scalar|null $simple = null;

    #[SharedAmongstTranslations]
    #[ORM\ManyToOne(targetEntity: Scalar::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    private Scalar|null $shared = null;

    #[EmptyOnTranslate]
    #[ORM\ManyToOne(targetEntity: Scalar::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    private Scalar|null $empty = null;


    public function getId(): int|null
    {
        return $this->id;
    }


    public function getSimple(): Scalar|null
    {
        return $this->simple;
    }


    public function setSimple(Scalar|null $simple = null): self
    {
        $this->simple = $simple;

        return $this;
    }

    public function getShared(): Scalar|null
    {
        return $this->shared;
    }

    public function setShared(Scalar|null $shared = null): self
    {
        $this->shared = $shared;

        return $this;
    }

    public function getEmpty(): Scalar|null
    {
        return $this->empty;
    }

    public function setEmpty(Scalar|null $empty = null): self
    {
        $this->empty = $empty;

        return $this;
    }
}
