<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Model;

use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * A translation root: one NON-translatable row per logical object, owning the Tuuid
 * that every locale variant of that object copies. Data that belongs to the object
 * rather than to a language -- children, foreign keys, shared scalars -- hangs off
 * this row, so Doctrine cascades it natively instead of the application keying it by
 * a bare `tuuid` string with no foreign key.
 *
 * A root is deliberately NOT a {@see TranslatableInterface}: it has no locale, so
 * neither the default-locale stamping in `TranslatableEventSubscriber::postLoad()`
 * nor the orphan heuristic in `prePersist()` may ever run for it. It gets its own,
 * smaller trait ({@see TranslationRootTrait}) with no listener attached.
 *
 * A translation row declares its root STRUCTURALLY: a `#[ORM\ManyToOne]` property
 * whose declared type implements this interface is a root reference
 * ({@see \Tmi\TranslationBundle\Utils\AttributeHelper::isTranslationRootReference()}),
 * with or without the optional {@see \Tmi\TranslationBundle\Doctrine\Attribute\TranslationRoot}
 * marker. `translate()` then reaffirms the reference to the identical instance on every
 * clone, and `tmi:translation:adopt-root` creates roots for the rows that already exist.
 */
interface TranslationRootInterface
{
    /**
     * Whether an identity has been minted or adopted yet. Never mints one.
     */
    public function hasTuuid(): bool;

    /**
     * Gives a fresh root an EXISTING group's identity -- the migration path
     * `tmi:translation:adopt-root` takes for every group that predates its root. The
     * same value again is a no-op (Doctrine re-hydrating the row); a different value
     * throws. Minting a brand-new identity is the trait's `mintTuuid()`, deliberately
     * not part of this contract: the bundle never mints on behalf of a root.
     *
     * @throws \LogicException when the root already carries a different Tuuid
     */
    public function adoptTuuid(Tuuid $tuuid): void;

    /**
     * The identity every translation row of this object copies.
     *
     * Unlike {@see TranslatableInterface::getTuuid()} this never lazily mints: the
     * root is the authority the rows copy FROM, so reading it before it has an
     * identity is a programming error and surfaces as a LogicException.
     *
     * @throws \LogicException while no Tuuid has been minted or adopted
     */
    public function getTuuid(): Tuuid;
}
