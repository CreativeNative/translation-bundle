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
 * - Non-nullable built-in without a zero-value (iterable, object, callable, never...):
 *   throws LogicException
 * - Union type: uses first non-null type's default
 * - Intersection type: throws LogicException (never nullable, always object types)
 *
 * IMPORTANT: Always uses type defaults, never PHP declared defaults.
 * IMPORTANT: null is only ever returned for types that actually accept null. Returning
 * null for a non-nullable property would surface later as an opaque TypeError on
 * assignment instead of this class' actionable LogicException.
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
     * @throws \LogicException when the type cannot have a safe default (any non-nullable
     *                        type without a zero-value: enum, object, intersection,
     *                        iterable, callable, ...)
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

        // ReflectionIntersectionType: always an object type and never nullable by language
        // rules, so there is no safe default to hand back.
        throw new \LogicException(\sprintf('Property %s::$%s is an intersection type and cannot have a type-safe default. Remove #[EmptyOnTranslate] or use #[SharedAmongstTranslations].', $property->class, $property->name));
    }

    /**
     * Resolves a single named type to its default value.
     *
     * @throws \LogicException when the non-nullable type has no zero-value default
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

        // Built-in without a zero-value (iterable, object, callable, never, ...).
        // Only reachable for non-nullable types: resolve() returns null before this for
        // anything that allows null, and a non-nullable union has no null member.
        throw new \LogicException(\sprintf('Property %s::$%s is a non-nullable %s and cannot have a type-safe default. Make it nullable, remove #[EmptyOnTranslate], or use #[SharedAmongstTranslations].', $property->class, $property->name, $name));
    }

    /**
     * Resolves a union type by using the first non-null type's default.
     *
     * @throws \LogicException when the first non-null type has no zero-value default
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
