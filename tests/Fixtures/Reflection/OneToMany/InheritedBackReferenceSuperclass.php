<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Reflection\OneToMany;

/**
 * Declares the ManyToOne back-reference PRIVATE, one level above the concrete
 * child class -- the shape BidirectionalOneToManyHandler must resolve through
 * ReflectionHelper::getProperty() rather than `new \ReflectionProperty($class,
 * $name)`, which only ever looks at $class itself.
 *
 * Lives outside tests/Fixtures/Entity on purpose: the handler resolves this
 * property through plain reflection, never through Doctrine metadata, so
 * nothing here needs to be a mapped entity.
 */
abstract class InheritedBackReferenceSuperclass
{
    private object|null $parent = null;

    public function getParent(): object|null
    {
        return $this->parent;
    }

    public function setParent(object|null $parent): static
    {
        $this->parent = $parent;

        return $this;
    }
}
