<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Attribute;

/**
 * Entity-level attribute to control translation behavior.
 *
 * When applied to a class implementing TranslatableInterface, allows per-entity
 * override of the global copy_source configuration:
 * - null: use global config (default)
 * - true: clone source content when creating translations (v1.x behavior)
 * - false: start translations empty with type-safe defaults
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Translatable
{
    public function __construct(
        public readonly bool|null $copySource = null,
    ) {
    }
}
