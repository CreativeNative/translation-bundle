<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Utils;

/**
 * Where a mapped value lives on an entity: a property of the entity itself
 * (`$owner` null), or an inner property of an embeddable held by `$owner`.
 *
 * `SharedValueSynchronizer` and `LocaleCompletenessResolver` both walk the same
 * two shapes and both need the object to read the value from -- this is that one
 * answer, uninitialized embeddables included.
 */
final readonly class PropertyLocation
{
    public function __construct(
        public \ReflectionProperty|null $owner,
        public \ReflectionProperty $property,
    ) {
    }

    /**
     * The object holding the value: the entity itself, or -- for an inner property
     * of an embeddable -- the embeddable instance, which is null when the entity's
     * embedded property is uninitialized or holds no object. A caller treats null
     * as "no value there": nothing to compare, nothing filled.
     */
    public function holderOf(object $entity): object|null
    {
        if (null === $this->owner) {
            return $entity;
        }

        if (!$this->owner->isInitialized($entity)) {
            return null;
        }

        $embeddable = $this->owner->getValue($entity);

        return \is_object($embeddable) ? $embeddable : null;
    }
}
