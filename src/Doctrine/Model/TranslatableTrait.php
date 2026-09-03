<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\ValueObject\Tuuid;

trait TranslatableTrait
{
    // Both columns are NOT NULL: every persisted row must carry a Tuuid and a
    // locale (TranslatableEventSubscriber::prePersist() assigns both before
    // insert). The PHP properties stay nullable regardless -- a freshly
    // `new`-ed, not-yet-persisted entity legitimately has neither yet, and
    // Doctrine hydrates only ever-non-null values back out of a NOT NULL
    // column. TuuidType and TranslationDoctorCommand's "null-tuuid" check
    // are what actually surfaces a NULL that reached the database anyway
    // (a raw insert bypassing the entity layer).
    #[ORM\Column(type: 'tuuid', length: 36, nullable: false)]
    #[SharedAmongstTranslations]
    private Tuuid|null $tuuid = null;

    #[ORM\Column(type: Types::STRING, length: 16, nullable: false)]
    private string|null $locale = null;

    final public function generateTuuid(): void
    {
        if (null === $this->tuuid) {
            $this->tuuid = Tuuid::generate();
        }
    }

    /**
     * Returns whether a Tuuid has already been assigned.
     *
     * Unlike {@see getTuuid()}, this never auto-generates a value — it is the
     * only safe way to detect the "no shared Tuuid yet" state before persist.
     */
    final public function hasTuuid(): bool
    {
        return null !== $this->tuuid;
    }

    /**
     * Set the Translation UUID.
     */
    final public function setTuuid(Tuuid|null $tuuid): self
    {
        // Initial assignment always allowed (including null for cloning/tests)
        if (null === $this->tuuid) {
            $this->tuuid = $tuuid;

            return $this;
        }

        // Doctrine rehydration of the same value:
        // - Only applies if both sides are Tuuid instances
        // - Compare via Tuuid::equals()
        if ($tuuid instanceof Tuuid && $this->tuuid->equals($tuuid)) {
            return $this;
        }

        // Everything else would reassign a previously set Tuuid
        throw new \LogicException('Tuuid is immutable and cannot be reassigned.');
    }

    /**
     * Returns entity's Translation UUID.
     */
    final public function getTuuid(): Tuuid
    {
        if (null === $this->tuuid) {
            $this->tuuid = Tuuid::generate();
        }

        return $this->tuuid;
    }

    final public function setLocale(string|null $locale = null): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Returns entity's locale.
     */
    final public function getLocale(): string|null
    {
        return $this->locale;
    }
}
