<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A second concrete subclass sharing {@see StiRoot}'s table, with no
 * #[SharedAmongstTranslations] property of its own — proves a mixed-subclass
 * hierarchy is still walked as one table, and that a class without any shared
 * property does not stop a sibling class's shared property from being seen.
 */
#[ORM\Entity]
class StiToy extends StiRoot
{
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $material = null;

    public function setMaterial(string|null $material = null): self
    {
        $this->material = $material;

        return $this;
    }

    public function getMaterial(): string|null
    {
        return $this->material;
    }
}
