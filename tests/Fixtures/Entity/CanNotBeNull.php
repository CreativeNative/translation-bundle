<?php

namespace TMI\TranslationBundle\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
#[ORM\Table]
class CanNotBeNull implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[EmptyOnTranslate]
    #[ORM\Column(type: 'string')]
    protected ?string $empty_not_nullable = null;

    public function setEmptyNotNullable(?string $empty_not_nullable = null): CanNotBeNull
    {
        $this->empty_not_nullable = $empty_not_nullable;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmptyNotNullable(): string
    {
        return $this->empty_not_nullable;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
}
