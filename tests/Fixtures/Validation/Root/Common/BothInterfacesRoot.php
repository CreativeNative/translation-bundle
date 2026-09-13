<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\Root\Common;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * The forbidden root shape: a class that is BOTH a translation root and a
 * translatable row. The two traits cannot be mixed (their methods collide), so
 * the methods are written out -- the point is the two interfaces on one type.
 */
class BothInterfacesRoot implements TranslationRootInterface, TranslatableInterface
{
    private Tuuid|null $tuuid = null;

    private string|null $locale = null;

    #[\Override]
    public function hasTuuid(): bool
    {
        return null !== $this->tuuid;
    }

    #[\Override]
    public function adoptTuuid(Tuuid $tuuid): void
    {
        $this->tuuid = $tuuid;
    }

    #[\Override]
    public function getTuuid(): Tuuid
    {
        return $this->tuuid ??= Tuuid::generate();
    }

    #[\Override]
    public function generateTuuid(): void
    {
        $this->tuuid ??= Tuuid::generate();
    }

    #[\Override]
    public function getLocale(): string|null
    {
        return $this->locale;
    }

    #[\Override]
    public function setLocale(string|null $locale = null): self
    {
        $this->locale = $locale;

        return $this;
    }
}
