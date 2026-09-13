<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Attribute;

/**
 * Optional marker for a translation row's root reference -- the `#[ORM\ManyToOne]`
 * property whose declared type implements
 * {@see \Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface}.
 *
 * It is documentation of intent and a validation hook, never the signal itself: a
 * property is a root reference by its TYPE
 * ({@see \Tmi\TranslationBundle\Utils\AttributeHelper::isTranslationRootReference()}),
 * with or without this attribute. The silent failure the root contract exists to close
 * is a forgotten attribute, and renaming the attribute does not close it -- deriving
 * sharedness from the referenced class's own type does.
 *
 * Placing it on a property that is NOT a root reference (wrong association kind, a type
 * that does not implement the interface) fails compile-time validation
 * (`TranslationRootContractException::forMarkerWithoutRoot()`), so the one line a reader
 * of the entity sees can never lie.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class TranslationRoot
{
}
