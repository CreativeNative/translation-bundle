<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Model;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * The Tuuid column of a {@see TranslationRootInterface} entity, and the only two ways
 * an identity gets onto it.
 *
 * - {@see mintTuuid()} is for a brand-new object: the ONE place an identity is born.
 * - {@see adoptTuuid()} is the migration path: a fresh root takes over an EXISTING
 *   group's Tuuid (`tmi:translation:adopt-root`). Same value again is a no-op -- that is
 *   Doctrine re-hydrating the row -- a different value throws.
 *
 * A root asked to do both, or either twice with a different value, throws; it never
 * silently re-identifies itself. {@see getTuuid()} never lazily mints (the deliberate
 * asymmetry with {@see TranslatableTrait::getTuuid()}, whose lazy mint stays for classes
 * that declare no root).
 *
 * The column is `unique`: one root per identity. It lives in the trait, as
 * `TranslatableTrait` does for `tuuid`/`locale`, because the property has to exist
 * physically on one class and an abstract SINGLE_TABLE root declares it once for every
 * leaf.
 *
 * Deliberately not PHP `readonly`: Doctrine's ReflectionReadonlyProperty tolerates a
 * second write only by object identity (`!==`), never by value, so a re-hydration that
 * constructs a logically equal Tuuid instance would throw. The guard below compares
 * with Tuuid::equals(), exactly as TranslatableTrait::setTuuid() does.
 */
trait TranslationRootTrait
{
    #[ORM\Column(type: 'tuuid', length: 36, nullable: false, unique: true)]
    private Tuuid|null $tuuid = null;

    final public function hasTuuid(): bool
    {
        return null !== $this->tuuid;
    }

    /**
     * Gives a brand-new root a brand-new identity.
     *
     * @throws \LogicException when the root already carries one
     */
    final public function mintTuuid(): void
    {
        if (null !== $this->tuuid) {
            throw new \LogicException(sprintf('%s already carries tuuid %s; a translation root is identified exactly once -- mintTuuid() is for a brand-new object, adoptTuuid() for taking over an existing group.', static::class, $this->tuuid));
        }

        $this->tuuid = Tuuid::generate();
    }

    /**
     * Gives a fresh root an EXISTING group's identity. The same value again is a no-op
     * (Doctrine hydration); a different value throws.
     *
     * @throws \LogicException when the root already carries a different Tuuid
     */
    final public function adoptTuuid(Tuuid $tuuid): void
    {
        if (null === $this->tuuid) {
            $this->tuuid = $tuuid;

            return;
        }

        if ($this->tuuid->equals($tuuid)) {
            return;
        }

        throw new \LogicException(sprintf('%s already carries tuuid %s and cannot adopt %s; a translation root is identified exactly once.', static::class, $this->tuuid, $tuuid));
    }

    /**
     * @throws \LogicException while neither minted nor adopted -- never lazily mints
     */
    final public function getTuuid(): Tuuid
    {
        if (null === $this->tuuid) {
            throw new \LogicException(sprintf('%s has no tuuid yet: call mintTuuid() on a new root, or adoptTuuid() to take over an existing group -- a translation root never mints one lazily, because its translation rows copy their identity from it.', static::class));
        }

        return $this->tuuid;
    }
}
