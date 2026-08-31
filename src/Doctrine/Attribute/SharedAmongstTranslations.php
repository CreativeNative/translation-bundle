<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Attribute;

/**
 * Copies the source value onto a new locale variant when the variant is
 * created via translate(), and marks the property for retroactive
 * reconciliation through the `tmi:translation:sync-shared` command.
 *
 * This is copy-on-translate, NOT an enforced invariant: once a variant
 * exists, writing the property on one locale diverges it silently. That is
 * deliberate — consumers may legitimately vary such values per locale (e.g.
 * publishing one language at a time). When divergence must be caught, gate
 * CI on `tmi:translation:sync-shared --check`, which exits non-zero on drift.
 *
 * Intentionally inert on a class that does not implement TranslatableInterface:
 * nothing in the bundle reads this attribute outside the translate() pipeline
 * (handlers, AttributeHelper, `tmi:translation:sync-shared`), and that pipeline
 * only ever runs for translatable entities and embeddables. That inertness is
 * a feature, not an oversight — it is what lets a trait shared between
 * translatable and non-translatable classes (e.g. a GeoLocatableTrait mixed
 * into both) declare the attribute once on its property and have it apply
 * only where it is meaningful, with no effect and no validation error on the
 * plain classes that merely reuse the trait.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS)]
class SharedAmongstTranslations
{
}
