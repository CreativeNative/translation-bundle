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
}
