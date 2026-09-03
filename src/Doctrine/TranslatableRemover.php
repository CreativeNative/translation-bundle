<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * Removes locale variants of a translatable entity.
 *
 * `$em->remove()` acts on one row; nothing links its sibling locale variants
 * for Doctrine to cascade through on its own — that is the incident this
 * class exists to prevent: a naive "delete this" that only ever sees, and
 * only ever removes, the current-locale row, leaving every other locale's
 * copy of the same content online.
 *
 * Every removal goes through `EntityManager::remove()` per variant — never a
 * bulk DQL DELETE — so ORM cascades, `orphanRemoval`, and lifecycle callbacks
 * fire exactly as they would for a single `remove()` call, once per variant.
 *
 * Holds process-local state across calls (a re-entrancy guard and an
 * exemption map), like {@see \Tmi\TranslationBundle\Translation\EntityTranslator} —
 * this is a long-lived service, not a stateless value.
 */
final class TranslatableRemover
{
    /**
     * Tuuids whose siblings are currently being scheduled for removal, keyed
     * by the Tuuid's string value — Tuuid is a value object, so identity
     * comparison would never match here.
     *
     * Guards {@see cascadeFromPreRemove()} against re-entering for a Tuuid
     * that {@see removeAllLocaleVariants()} (or an outer
     * `cascadeFromPreRemove()` call) is already in the middle of processing:
     * `EntityManager::remove()` fires `preRemove` synchronously, before it
     * returns, so the opt-in cascade listener built on this class would
     * otherwise see every sibling again for each sibling it removes.
     *
     * @var array<string, true>
     */
    private array $inProgress = [];

    /**
     * Entities exempted, for the duration of one `remove()` call, from the
     * opt-in cascade built on {@see cascadeFromPreRemove()} — keyed by object
     * identity, because a Tuuid-keyed guard would exempt every sibling too,
     * not just the one variant {@see removeSingleLocaleVariant()} was asked
     * to remove.
     *
     * @var \WeakMap<TranslatableInterface, true>
     */
    private \WeakMap $exempt;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LocaleVariantFinder $finder,
    ) {
        $this->exempt = new \WeakMap();
    }

    /**
     * Schedules every locale variant sharing `$entity`'s Tuuid for removal —
     * `$entity` included — via `EntityManager::remove()` per variant. Does
     * not flush.
     *
     * @return list<TranslatableInterface> the variants scheduled for removal
     */
    public function removeAllLocaleVariants(TranslatableInterface $entity): array
    {
        $tuuid = $entity->getTuuid();
        $key   = $tuuid->getValue();

        $this->inProgress[$key] = true;

        try {
            $variants = array_values($this->finder->findAllLocaleVariants($entity::class, $tuuid));

            if (!\in_array($entity, $variants, true)) {
                $variants[] = $entity;
            }

            foreach ($variants as $variant) {
                $this->entityManager->remove($variant);
            }

            return $variants;
        } finally {
            unset($this->inProgress[$key]);
        }
    }

    /**
     * Removes only `$entity`, exempting it from the opt-in
     * `cascade_remove_locale_variants` listener for this call so its sibling
     * locale variants are left untouched.
     */
    public function removeSingleLocaleVariant(TranslatableInterface $entity): void
    {
        $this->exempt[$entity] = true;

        try {
            $this->entityManager->remove($entity);
        } finally {
            unset($this->exempt[$entity]);
        }
    }

    /**
     * Schedules `$entity`'s sibling locale variants for removal — never
     * `$entity` itself — in response to `$entity` being removed. Meant to be
     * called from a `preRemove` listener for every translatable entity
     * Doctrine schedules for deletion; a no-op when `$entity` was exempted
     * via {@see removeSingleLocaleVariant()}, or when its Tuuid is already
     * being processed by an outer call.
     *
     * Reserved for that listener because calling it directly, outside a
     * `preRemove` hook, schedules siblings for an entity that was never
     * itself scheduled for removal:
     *
     * - `UnitOfWork::doRemove()` gives every top-level `remove()` call its
     *   own `$visited` set, so a `remove()` call nested inside this method
     *   cannot see that `$entity` is already mid-removal;
     * - `preRemove` listeners run — and this method with them — *before*
     *   `scheduleForDelete()` marks `$entity` itself as scheduled, so
     *   `$entity` is still MANAGED here; calling `remove($entity)` again
     *   from inside this method would fire its `preRemove` a second time;
     * - `remove()` on an entity already in `STATE_REMOVED` is a no-op, so
     *   that is not what stops the recursion above — the `$inProgress` guard
     *   is; without it, each sibling's own removal would re-discover every
     *   other sibling (including the ones already being removed) and try to
     *   remove them again.
     *
     * @internal only the opt-in cascade removal listener should call this
     */
    public function cascadeFromPreRemove(TranslatableInterface $entity): void
    {
        if (isset($this->exempt[$entity])) {
            return;
        }

        $tuuid = $entity->getTuuid();
        $key   = $tuuid->getValue();

        if (isset($this->inProgress[$key])) {
            return;
        }

        $this->inProgress[$key] = true;

        try {
            foreach ($this->finder->findAllLocaleVariants($entity::class, $tuuid) as $variant) {
                if ($variant !== $entity) {
                    $this->entityManager->remove($variant);
                }
            }
        } finally {
            unset($this->inProgress[$key]);
        }
    }
}
