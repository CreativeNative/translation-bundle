<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation;

/**
 * Resolves type-safe default values for properties based on their type declarations.
 *
 * Resolution rules:
 * - No type declaration: null
 * - Nullable type (allowsNull()): null
 * - Non-nullable built-in scalars: zero-value (string='', int=0, float=0.0, bool=false, array=[])
 * - Non-nullable enum: throws LogicException
 * - Non-nullable object: throws LogicException
 * - Union type: uses first non-null type's default
 * - Intersection type: null (always object types)
 *
 * IMPORTANT: Always uses type defaults, never PHP declared defaults.
 */
final readonly class TypeDefaultResolver
{
    /**
     * Map of built-in type names to their zero-value defaults.
     *
     * @var array<string, mixed>
     */
    private const array SCALAR_DEFAULTS = [
        'string' => '',
        'int'    => 0,
        'float'  => 0.0,
        'bool'   => false,
        'array'  => [],
    ];

    /**
     * Resolves the type-safe default for a property.
     *
     * @throws \LogicException when the type cannot have a safe default (non-nullable enum/object)
     */
    public function resolve(\ReflectionProperty $property): mixed
    {
        $type = $property->getType();

        // No type declaration: return null
        if (null === $type) {
            return null;
        }

        // Nullable types always get null (includes ?string, string|null, etc.)
        // IMPORTANT: Check allowsNull() FIRST -- ?string is ReflectionNamedType, not ReflectionUnionType
        if ($type->allowsNull()) {
            return null;
        }

        // ReflectionNamedType: single type
        if ($type instanceof \ReflectionNamedType) {
            return $this->resolveNamedType($type, $property);
        }

        // ReflectionUnionType: use first non-null type's default
        if ($type instanceof \ReflectionUnionType) {
            return $this->resolveUnionType($type, $property);
        }

        // ReflectionIntersectionType: always an object type, cannot have safe default
        return null;
    }

    /**
     * Resolves a single named type to its default value.
     *
     * @throws \LogicException when the type is a non-nullable enum or object
     */
    private function resolveNamedType(\ReflectionNamedType $type, \ReflectionProperty $property): mixed
    {
        $name = $type->getName();

        // Built-in scalar types (string, int, float, bool, array)
        if ($type->isBuiltin() && isset(self::SCALAR_DEFAULTS[$name])) {
            return self::SCALAR_DEFAULTS[$name];
        }

        // Non-built-in: check if it's an enum
        if (!$type->isBuiltin() && enum_exists($name)) {
            throw new \LogicException(\sprintf('Property %s::$%s is a non-nullable enum and cannot have a type-safe default. Make it nullable or use #[SharedAmongstTranslations].', $property->class, $property->name));
        }

        // Non-built-in, non-enum: it's an object (DateTime, custom class, etc.)
        if (!$type->isBuiltin()) {
            throw new \LogicException(\sprintf('Property %s::$%s is a non-nullable object and cannot have a type-safe default. Make it nullable, remove #[EmptyOnTranslate], or use #[SharedAmongstTranslations].', $property->class, $property->name));
        }

        // Unknown built-in type (safety fallback)
        return null;
    }

    /**
     * Resolves a union type by using the first non-null type's default.
     *
     * @throws \LogicException when the first non-null type is a non-nullable enum or object
     */
    private function resolveUnionType(\ReflectionUnionType $type, \ReflectionProperty $property): mixed
    {
        foreach ($type->getTypes() as $subType) {
            if ($subType instanceof \ReflectionNamedType && 'null' !== $subType->getName()) {
                return $this->resolveNamedType($subType, $property);
            }
        }

        // @codeCoverageIgnoreStart
        // PHP requires at least one non-null type in a non-nullable union, making this unreachable
        return null;
        // @codeCoverageIgnoreEnd
    }
}
