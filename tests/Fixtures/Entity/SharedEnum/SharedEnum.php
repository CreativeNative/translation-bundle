<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\SharedEnum;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Enum\Priority;

/**
 * Fixture with an enum-valued #[SharedAmongstTranslations] column. Enums are
 * immutable singletons and cannot be cloned (`clone` throws an Error), so a
 * copy path that blindly clones every object value fails on this fixture.
 */
#[ORM\Entity]
class SharedEnum implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $title = null;

    #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::STRING, nullable: true, enumType: Priority::class)]
    private Priority|null $priority = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setTitle(string|null $title = null): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): string|null
    {
        return $this->title;
    }

    public function setPriority(Priority|null $priority = null): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getPriority(): Priority|null
    {
        return $this->priority;
    }
}
