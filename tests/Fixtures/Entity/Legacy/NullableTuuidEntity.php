<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Legacy;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * Implements TranslatableInterface without TranslatableTrait, with a nullable
 * `tuuid` column -- the shape a row can be in when it was written by a raw
 * insert that bypassed the entity layer entirely (a legacy migration, a
 * hand-rolled import script).
 *
 * TranslatableTrait always assigns a Tuuid before persist, and TuuidType's
 * convertToDatabaseValue() invents a fresh one for a null PHP value, so
 * neither the trait nor a normal persist() through this very class can ever
 * write a literal database NULL here -- tests reach that state with a DBAL
 * insert() that bypasses Doctrine's Type conversion (no $types argument), the
 * same way a raw SQL migration would.
 *
 * Regression fixture for TranslationDoctorCommand's "null-tuuid" anomaly.
 */
#[ORM\Entity]
class NullableTuuidEntity implements TranslatableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int|null $id = null;

    #[ORM\Column(type: 'tuuid', length: 36, nullable: true)]
    private Tuuid|null $tuuid = null;

    #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
    private string|null $locale = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function generateTuuid(): void
    {
        if (null === $this->tuuid) {
            $this->tuuid = Tuuid::generate();
        }
    }

    public function hasTuuid(): bool
    {
        return null !== $this->tuuid;
    }

    public function getTuuid(): Tuuid
    {
        if (null === $this->tuuid) {
            $this->generateTuuid();
        }

        // PHPStan doesn't understand that generateTuuid() guarantees non-null
        assert(null !== $this->tuuid);

        return $this->tuuid;
    }

    public function getLocale(): string|null
    {
        return $this->locale;
    }

    public function setLocale(string|null $locale = null): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getTranslations(): array
    {
        return [];
    }
}
