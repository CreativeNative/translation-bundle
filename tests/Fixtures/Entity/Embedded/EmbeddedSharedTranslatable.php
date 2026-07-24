<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Embedded;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Embeddables whose sharing is declared BELOW the entity property: on the embeddable class
 * (classShared) and on an inner property (propertyShared). Neither entity property carries
 * #[SharedAmongstTranslations] itself.
 */
#[ORM\Entity]
class EmbeddedSharedTranslatable implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $title = null;

    #[ORM\Embedded(class: SharedClassEmbeddable::class)]
    private SharedClassEmbeddable $classShared;

    #[ORM\Embedded(class: PropertySharedEmbeddable::class)]
    private PropertySharedEmbeddable $propertyShared;

    public function __construct()
    {
        $this->classShared    = new SharedClassEmbeddable();
        $this->propertyShared = new PropertySharedEmbeddable();
    }

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

    public function getClassShared(): SharedClassEmbeddable
    {
        return $this->classShared;
    }

    public function setClassShared(SharedClassEmbeddable $classShared): self
    {
        $this->classShared = $classShared;

        return $this;
    }

    public function getPropertyShared(): PropertySharedEmbeddable
    {
        return $this->propertyShared;
    }

    public function setPropertyShared(PropertySharedEmbeddable $propertyShared): self
    {
        $this->propertyShared = $propertyShared;

        return $this;
    }
}
