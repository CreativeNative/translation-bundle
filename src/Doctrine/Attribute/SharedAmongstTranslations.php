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
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS)]
class SharedAmongstTranslations
{
}
