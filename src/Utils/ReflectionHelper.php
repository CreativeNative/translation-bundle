<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Utils;

use Doctrine\Persistence\Proxy;

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
     * Per real (non-proxy) class name. A hierarchy walk is class metadata,
     * immutable for the life of the process, and every caller listed on
     * getHierarchyProperties() below re-walks it on every property access
     * without this cache -- once per translate() call, per handler.
     *
     * @var array<class-string, list<\ReflectionProperty>>
     */
    private static array $hierarchyCache = [];

    /**
     * All properties of the class, including private properties of parent classes.
     *
     * Deduplicated by name, child class first — when a child redeclares (shadows)
     * a parent's private property, the child's wins, matching what property
     * accessors resolve.
     *
     * A classic (non-native-lazy-object) Doctrine proxy subclasses the real
     * entity and adds its own generated properties (initializer closure,
     * `__isInitialized__`); walking from the proxy class would fold those
     * into the result as if they belonged to the entity, and cache them
     * under the wrong key besides. Same reason EntityTranslator::
     * resolveCopySource() and DoctrineObjectHandler::supports() resolve to
     * the parent class first (WP7 #16) -- PHP attributes are never
     * inherited by a generated subclass either, so #[Translatable] would be
     * invisible from the proxy class just the same.
     *
     * @param \ReflectionClass<covariant object> $class
     *
     * @return list<\ReflectionProperty>
     */
    public static function getHierarchyProperties(\ReflectionClass $class): array
    {
        if ($class->implementsInterface(Proxy::class)) {
            $parent = $class->getParentClass();
            if (false !== $parent) {
                $class = $parent;
            }
        }

        $cacheKey = $class->getName();

        if (isset(self::$hierarchyCache[$cacheKey])) {
            return self::$hierarchyCache[$cacheKey];
        }

        /** @var array<string, \ReflectionProperty> $properties */
        $properties = [];

        $current = $class;
        do {
            foreach ($current->getProperties() as $property) {
                $properties[$property->getName()] ??= $property;
            }
            $current = $current->getParentClass();
        } while (false !== $current);

        return self::$hierarchyCache[$cacheKey] = array_values($properties);
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
