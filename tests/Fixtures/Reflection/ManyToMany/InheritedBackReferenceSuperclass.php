<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Reflection\ManyToMany;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Declares the ManyToMany back-reference collection PRIVATE, one level above
 * the concrete item class -- the shape BidirectionalManyToManyHandler must
 * resolve through ReflectionHelper::getProperty() rather than
 * `new \ReflectionProperty($class, $name)`, which only ever looks at $class
 * itself.
 *
 * Lives outside tests/Fixtures/Entity on purpose: the handler resolves this
 * property through plain reflection, never through Doctrine metadata, so
 * nothing here needs to be a mapped entity.
 */
abstract class InheritedBackReferenceSuperclass
{
    /** @var Collection<int, object> */
    private Collection $parents;

    public function __construct()
    {
        $this->parents = new ArrayCollection();
    }

    /** @return Collection<int, object> */
    public function getParents(): Collection
    {
        return $this->parents;
    }
}
