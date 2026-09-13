<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;

/**
 * The two steps every collection handler shares -- bidirectional OneToMany, both
 * ManyToMany shapes, and a custom handler for an application's own collection
 * shape: the batched lookup before the loop, and the cycle-guard test inside it.
 * Back-reference repair stays handler-specific (a to-one FK versus a to-many
 * collection), which is why this is a helper and not a base class.
 */
final class CollectionTranslationSupport
{
    /**
     * Hands the whole collection to {@see EntityTranslatorInterface::preload()} once,
     * before the loop: one batched lookup query per item class rather than one per
     * item, since each item is otherwise its own translate() call with its own
     * internal single-entity preload(). preload() ignores non-translatable items and
     * anything already cached, so a mixed collection is safe to hand it whole. A
     * missing target locale (never the case for a call that came through
     * EntityTranslator) is a no-op.
     *
     * @param iterable<mixed> $items
     */
    public static function preload(EntityTranslatorInterface $translator, iterable $items, string|null $targetLocale): void
    {
        if (\is_string($targetLocale)) {
            $translator->preload($items, $targetLocale);
        }
    }

    /**
     * Whether the translator handed $item back untranslated because its
     * (tuuid, targetLocale) pair is already marked in-progress higher up this very
     * call -- reachable in the most ordinary shape since the to-one clones run the
     * full entity pipeline: translating a child recurses into its own ManyToOne
     * parent, which recurses into the parent's clone, which lands right back at the
     * SAME child, still mid-translation.
     *
     * A handler that meets this must skip the item outright. Adding it would put the
     * SOURCE entity into the translated owner's collection, and any back-reference
     * write would repoint the SOURCE's own field at the translated owner -- mutating
     * an entity the caller is still holding a live reference to, for a flush neither
     * of them asked for. The translated owner's collection is simply missing this
     * item until a reload: an inverse-side collection is never persisted on its own
     * (Doctrine writes the owning side -- the child's FK, or the owning join field),
     * and Doctrine does not retroactively complete an inverse collection from a write
     * that went through a different entity instance, so a reload of either side
     * always shows the complete, correct set.
     *
     * An instance handed back unchanged but already carrying the target locale is
     * NOT this fallback -- it is a genuine existing translation -- and is kept.
     */
    public static function isCycleGuardFallback(mixed $result, TranslatableInterface $item, string|null $targetLocale): bool
    {
        return $result === $item && $item->getLocale() !== $targetLocale;
    }
}
