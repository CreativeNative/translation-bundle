<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Inheritance;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Mapped superclass declaring the generated id as a PRIVATE property.
 *
 * This is the shape ReflectionClass::getProperties() on a child class never
 * lists — the regression fixture for resetGeneratedIds() walking the full
 * class hierarchy.
 */
#[ORM\MappedSuperclass]
abstract class PrivateIdSuperclass
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int|null $id = null;

    public function getId(): int|null
    {
        return $this->id;
    }
}
