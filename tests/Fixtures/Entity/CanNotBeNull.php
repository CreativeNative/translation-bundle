<?php

namespace TMI\TranslationBundle\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
#[ORM\Table]
final class CanNotBeNull implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[EmptyOnTranslate]
    #[ORM\Column(type: 'string')]
    private ?string $emptyNotNullable = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setEmptyNotNullable(?string $emptyNotNullable = null): CanNotBeNull
    {
        $this->emptyNotNullable = $emptyNotNullable;

        return $this;
    }

    public function getEmptyNotNullable(): string|null
    {
        return $this->emptyNotNullable;
    }
}
