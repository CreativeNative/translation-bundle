<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Utils;

/**
 * Hierarchy-aware property reflection.
 *
 * ReflectionClass::getProperties() never lists private properties of parent
 * classes, so any per-property walk that uses it silently skips columns an
 * entity declares private on a mapped superclass. Every property iteration in
 * the bundle goes through this helper instead.
 */
final class ReflectionHelper
{
    /**
     * All properties of the class, including private properties of parent classes.
     *
     * Deduplicated by name, child class first — when a child redeclares (shadows)
     * a parent's private property, the child's wins, matching what property
     * accessors resolve.
     *
     * @param \ReflectionClass<covariant object> $class
     *
     * @return list<\ReflectionProperty>
     */
    public static function getHierarchyProperties(\ReflectionClass $class): array
    {
        /** @var array<string, \ReflectionProperty> $properties */
        $properties = [];

        $current = $class;
        do {
            foreach ($current->getProperties() as $property) {
                $properties[$property->getName()] ??= $property;
            }
            $current = $current->getParentClass();
        } while (false !== $current);

        return array_values($properties);
    }

    /**
     * A single named property, found by walking the hierarchy the same way
     * getHierarchyProperties() does.
     *
     * `new \ReflectionProperty($class, $name)` only ever looks at $class itself: a
     * private property declared on a mapped superclass above it throws
     * ReflectionException there, even though the property genuinely exists on
     * every instance of $class. Every single-property lookup by class-and-name
     * in the bundle goes through this helper instead.
     *
     * @param \ReflectionClass<covariant object>|class-string $class
     *
     * @throws \ReflectionException when $name exists on neither $class nor any parent
     */
    public static function getProperty(\ReflectionClass|string $class, string $name): \ReflectionProperty
    {
        $reflect = $class instanceof \ReflectionClass ? $class : new \ReflectionClass($class);

        $current = $reflect;
        do {
            if ($current->hasProperty($name)) {
                return $current->getProperty($name);
            }
            $current = $current->getParentClass();
        } while (false !== $current);

        throw new \ReflectionException(sprintf('Property "%s" does not exist on class "%s" or any of its parent classes.', $name, $reflect->getName()));
    }
}
