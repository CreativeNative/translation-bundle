<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Root;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;

/**
 * The application's side of `tmi:translation:adopt-root`: the bundle knows how
 * to stream a table in Tuuid groups, classify each group and adopt an identity, but
 * only the application knows WHICH concrete root class a group needs (a SINGLE_TABLE
 * discriminator, say), how to build one, and how to attach it to a row that was
 * constructed before the root existed.
 *
 * Register one implementation per translatable hierarchy, tagged
 * `tmi_translation.root_adopter` with the required attribute `class` naming
 * {@see getTranslatableClass()}'s value -- the tag attribute is what the container
 * cross-checks at compile time (every class with a root reference has exactly one
 * adopter, every adopter names a class that has one), without instantiating anything;
 * {@see RootAdopterRegistry::addAdopter()} refuses an adopter whose method disagrees
 * with its tag.
 */
interface RootAdopterInterface
{
    /**
     * The translatable hierarchy this adopter serves, e.g. `Property::class`. The
     * command always streams the Doctrine hierarchy ROOT of this class, so a group
     * whose rows span two leaves is seen whole.
     *
     * @return class-string<TranslatableInterface>
     */
    public function getTranslatableClass(): string;

    /**
     * The root already attached to $row, or null. Must tolerate an uninitialized typed
     * property (phase 1: `Listing|null $listing = null`, or no default at all).
     */
    public function getRoot(TranslatableInterface $row): TranslationRootInterface|null;

    /**
     * A NEW root for one group, WITHOUT a Tuuid -- the command adopts the group's. The
     * concrete class may depend on the rows (STI: from the discriminator). Returning a
     * root that already carries a Tuuid, or one that is not an instance of
     * {@see rootClassFor()} for the group's rows, is refused by the command
     * ({@see \Tmi\TranslationBundle\Exception\RootAdoptionException}).
     *
     * @param non-empty-list<TranslatableInterface> $group every locale variant of one Tuuid
     */
    public function createRootFor(array $group): TranslationRootInterface;

    /**
     * Attach $root to $row without a constructor (reflection- or setter-based). The
     * command calls this for every row of a NEW group and for the rows a PARTIAL group is
     * missing; never for a row that already has a root.
     */
    public function attach(TranslatableInterface $row, TranslationRootInterface $root): void;

    /**
     * The concrete root class this row implies (STI: from its discriminator). Every row
     * of a group must agree, and {@see createRootFor()}'s result must be an instance of
     * it -- otherwise the command refuses the group.
     *
     * @return class-string<TranslationRootInterface>
     */
    public function rootClassFor(TranslatableInterface $row): string;

    /**
     * Everything that must agree inside one group beyond the root class (e.g. the
     * domain family). Rows with different keys form a MISMATCHED group: never adopted,
     * always a `--check` failure. A pre-migration audit the application would otherwise
     * script by hand becomes a standing guarantee. Return '' when nothing else has to
     * agree.
     */
    public function coherenceKey(TranslatableInterface $row): string;
}
