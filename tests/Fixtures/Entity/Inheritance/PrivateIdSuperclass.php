<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Inheritance;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;

/**
 * Mapped superclass declaring the generated id — and translatable columns —
 * as PRIVATE properties.
 *
 * This is the shape ReflectionClass::getProperties() on a child class never
 * lists: the regression fixture for every property walk going through
 * ReflectionHelper::getHierarchyProperties() (id reset, translation pipeline,
 * completeness, shared back-fill).
 */
#[ORM\MappedSuperclass]
abstract class PrivateIdSuperclass
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $notes = null;

    #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $sharedCode = null;

    #[EmptyOnTranslate]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $ephemeral = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setNotes(string|null $notes = null): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function getNotes(): string|null
    {
        return $this->notes;
    }

    public function setSharedCode(string|null $sharedCode = null): self
    {
        $this->sharedCode = $sharedCode;

        return $this;
    }

    public function getSharedCode(): string|null
    {
        return $this->sharedCode;
    }

    public function setEphemeral(string|null $ephemeral = null): self
    {
        $this->ephemeral = $ephemeral;

        return $this;
    }

    public function getEphemeral(): string|null
    {
        return $this->ephemeral;
    }
}
