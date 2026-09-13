<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Attribute;

/**
 * Copies the source value onto a new locale variant when the variant is
 * created via translate(), and marks the property for retroactive
 * reconciliation through the `tmi:translation:sync-shared` command.
 *
 * By default this is an enforced invariant, not only a copy-on-translate:
 * `propagate_shared_on_flush` is on, so
 * {@see \Tmi\TranslationBundle\Doctrine\EventListener\SharedValuePropagationListener}
 * copies a change made on ANY locale variant onto every sibling inside the same
 * flush(), whatever code performed the edit, and
 * {@see \Tmi\TranslationBundle\Doctrine\SharedValueSynchronizer} is that same
 * copy as a public service for application code. Mark a property with this
 * attribute only when every locale must agree on its value.
 *
 * With `propagate_shared_on_flush: false` the attribute degrades to
 * copy-on-translate: once a variant exists, writing the property on one locale
 * diverges it silently. That is a legitimate choice for an application that
 * varies such values per locale (e.g. publishing one language at a time); gate
 * CI on `tmi:translation:sync-shared --check`, which exits non-zero on drift,
 * and reconcile with the command itself.
 *
 * The class-level form (TARGET_CLASS) is honoured on EMBEDDABLES only: there it
 * shares every inner property that does not override it with #[EmptyOnTranslate].
 * On an entity class it is inert -- translate(), sync-shared and the flush-time
 * propagation all read the attribute per property; mark the properties instead.
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
final class SharedAmongstTranslations
{
}
