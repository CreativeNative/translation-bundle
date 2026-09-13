<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Utils;

use Doctrine\ORM\Mapping as ORM;
use Psr\Log\LoggerInterface;
use Tmi\TranslationBundle\Doctrine\Attribute as TranslationAttribute;
use Tmi\TranslationBundle\Doctrine\Attribute\Translatable;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;
use Tmi\TranslationBundle\Exception\AttributeConflictException;
use Tmi\TranslationBundle\Exception\ClassLevelAttributeConflictException;
use Tmi\TranslationBundle\Exception\ReadonlyPropertyException;
use Tmi\TranslationBundle\Exception\TranslationRootContractException;
use Tmi\TranslationBundle\Exception\ValidationException;

class AttributeHelper
{
    private const array DOCTRINE_ATTRIBUTES = [
        'isEmbedded'       => ORM\Embedded::class,
        'isOneToOne'       => ORM\OneToOne::class,
        'isId'             => ORM\Id::class,
        'isGeneratedValue' => ORM\GeneratedValue::class,
        'isManyToOne'      => ORM\ManyToOne::class,
        'isOneToMany'      => ORM\OneToMany::class,
        'isManyToMany'     => ORM\ManyToMany::class,
    ];

    private const array TRANSLATION_ATTRIBUTES = [
        'isSharedAmongstTranslations' => TranslationAttribute\SharedAmongstTranslations::class,
        'isEmptyOnTranslate'          => TranslationAttribute\EmptyOnTranslate::class,
        'hasTranslationRootMarker'    => TranslationAttribute\TranslationRoot::class,
    ];
    /** @var array<string, true> */
    private array $validatedProperties = [];

    /**
     * Per declaringClass::property -- the structural root-reference test reflects
     * the property's type and is asked on the translate() hot path, same
     * precedent as $attributeCache.
     *
     * @var array<string, bool>
     */
    private array $rootReferenceCache = [];

    /** @var array<string, true> */
    private array $validatedClasses = [];

    /**
     * Per declaringClass::property::attribute. #[Attribute] presence is a
     * class-level fact, immutable for the life of the process -- same
     * precedent as $validatedProperties -- and this is the single most
     * frequently called method in the translation hot path (called once per
     * property per attribute kind, per translate() call, uncached).
     *
     * @var array<string, bool>
     */
    private array $attributeCache = [];

    /**
     * Defines if the property is embedded.
     */
    public function isEmbedded(\ReflectionProperty $property): bool
    {
        return $this->hasAttribute($property, self::DOCTRINE_ATTRIBUTES[__FUNCTION__]);
    }

    /**
     * Defines if the property is to be shared amongst parents' translations.
     */
    public function isSharedAmongstTranslations(\ReflectionProperty $property): bool
    {
        return $this->hasAttribute($property, self::TRANSLATION_ATTRIBUTES[__FUNCTION__]);
    }

    /**
     * Defines if the property should be emptied on translate.
     */
    public function isEmptyOnTranslate(\ReflectionProperty $property): bool
    {
        return $this->hasAttribute($property, self::TRANSLATION_ATTRIBUTES[__FUNCTION__]);
    }

    /**
     * Whether the property carries the OPTIONAL #[TranslationRoot] marker. The marker
     * is documentation and a validation hook, never the signal -- see
     * {@see isTranslationRootReference()} for what actually makes a root reference.
     */
    public function hasTranslationRootMarker(\ReflectionProperty $property): bool
    {
        return $this->hasAttribute($property, self::TRANSLATION_ATTRIBUTES[__FUNCTION__]);
    }

    /**
     * The structural test for a translation row's root reference (5.1): a
     * `#[ORM\ManyToOne]` whose declared type implements {@see TranslationRootInterface}.
     *
     * ManyToOne only -- a root has two or more rows by construction, so OneToOne's
     * cardinality is never right and its idiomatic `unique` join column would refuse the
     * second locale's INSERT. The type is resolved with `is_a(..., true)` on the name and
     * never gated behind `class_exists()`: a property typed to the bare interface is a
     * legitimate declaration and `class_exists()` is false for it.
     *
     * Why structural, not the attribute: the silent failure the root contract closes is a
     * forgotten attribute. A pre-5.1 class cannot implement a 5.1 interface by accident,
     * so this test is false for every property in every existing application -- which is
     * what makes 5.1 provably opt-in.
     */
    public function isTranslationRootReference(\ReflectionProperty $property): bool
    {
        $cacheKey = $property->class.'::'.$property->name;

        return $this->rootReferenceCache[$cacheKey]
            ??= $this->isManyToOne($property) && null !== $this->translationRootType($property);
    }

    /**
     * The property's declared class-string when that type implements
     * {@see TranslationRootInterface}, whatever association kind (or none) the property
     * carries -- the half of the structural test the validation of a stray marker or a
     * wrongly mapped root reference needs on its own.
     *
     * @return class-string<TranslationRootInterface>|null
     */
    public function translationRootType(\ReflectionProperty $property): string|null
    {
        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name = $type->getName();

        if (!is_a($name, TranslationRootInterface::class, true)) {
            return null;
        }

        /** @var class-string<TranslationRootInterface> $name */
        return $name;
    }

    /**
     * Shared by attribute OR by being a root reference: what every consumer that can
     * see a to-one association asks (`EntityTranslator::runHandlers()`,
     * `SharedValueSynchronizer`), so a root reference is reaffirmed to the identical
     * instance on every translate() and compared by identity by `sync-shared --check`.
     * {@see isSharedAmongstTranslations()} stays literal to its name.
     */
    public function isEffectivelyShared(\ReflectionProperty $property): bool
    {
        return $this->isSharedAmongstTranslations($property) || $this->isTranslationRootReference($property);
    }

    /**
     * Defines if the property is a OneToOne relation.
     */
    public function isOneToOne(\ReflectionProperty $property): bool
    {
        return $this->hasAttribute($property, self::DOCTRINE_ATTRIBUTES[__FUNCTION__]);
    }

    /**
     * Defines if the property is an ID.
     */
    public function isId(\ReflectionProperty $property): bool
    {
        return $this->hasAttribute($property, self::DOCTRINE_ATTRIBUTES[__FUNCTION__]);
    }

    /**
     * Defines if the property has a generated value strategy.
     */
    public function isGeneratedValue(\ReflectionProperty $property): bool
    {
        return $this->hasAttribute($property, self::DOCTRINE_ATTRIBUTES[__FUNCTION__]);
    }

    /**
     * Defines if the property is a ManyToOne relation.
     */
    public function isManyToOne(\ReflectionProperty $property): bool
    {
        return $this->hasAttribute($property, self::DOCTRINE_ATTRIBUTES[__FUNCTION__]);
    }

    /**
     * Defines if the property is a OneToMany relation.
     */
    public function isOneToMany(\ReflectionProperty $property): bool
    {
        return $this->hasAttribute($property, self::DOCTRINE_ATTRIBUTES[__FUNCTION__]);
    }

    /**
     * Defines if the property is a ManyToMany relation.
     */
    public function isManyToMany(\ReflectionProperty $property): bool
    {
        return $this->hasAttribute($property, self::DOCTRINE_ATTRIBUTES[__FUNCTION__]);
    }

    /**
     * Defines if the property can be null.
     */
    public function isNullable(\ReflectionProperty $property): bool
    {
        $type = $property->getType();

        return null !== $type && $type->allowsNull();
    }

    /**
     * Checks if a class has the #[SharedAmongstTranslations] attribute at class level.
     *
     * @param \ReflectionClass<object> $class
     */
    public function classHasSharedAmongstTranslations(\ReflectionClass $class): bool
    {
        return [] !== $class->getAttributes(
            TranslationAttribute\SharedAmongstTranslations::class,
            \ReflectionAttribute::IS_INSTANCEOF,
        );
    }

    /**
     * Checks whether an embeddable carries #[SharedAmongstTranslations] in any of the
     * three places the bundle honours:
     * - on the entity property holding the embeddable, or
     * - on the embeddable class itself, or
     * - on any property inside the embeddable.
     *
     * Used by SharedValueSynchronizer -- the one discovery behind
     * tmi:translation:sync-shared, the flush-time propagation and
     * LocaleCompletenessResolver -- to decide which embedded values are shared across
     * locale variants; this must agree with how EmbeddedHandler resolves sharing at
     * translate time (three-level cascade in EmbeddedHandler::resolvePropertyAttribute()).
     *
     * @param \ReflectionClass<object> $embeddable
     */
    public function isEmbeddableShared(
        \ReflectionClass $embeddable,
        \ReflectionProperty|null $parentProperty = null,
    ): bool {
        if (null !== $parentProperty && $this->isSharedAmongstTranslations($parentProperty)) {
            return true;
        }

        if ($this->classHasSharedAmongstTranslations($embeddable)) {
            return true;
        }

        return array_any(
            ReflectionHelper::getHierarchyProperties($embeddable),
            $this->isSharedAmongstTranslations(...),
        );
    }

    /**
     * Checks if a class has the #[EmptyOnTranslate] attribute at class level.
     *
     * @param \ReflectionClass<object> $class
     */
    public function classHasEmptyOnTranslate(\ReflectionClass $class): bool
    {
        return [] !== $class->getAttributes(
            TranslationAttribute\EmptyOnTranslate::class,
            \ReflectionAttribute::IS_INSTANCEOF,
        );
    }

    /**
     * Validates an embeddable class for attribute conflicts.
     * Checks class-level attribute conflicts and validates all properties.
     * Results are cached per class name.
     *
     * @param \ReflectionClass<object> $class
     *
     * @throws ValidationException When validation errors are found
     */
    public function validateEmbeddableClass(
        \ReflectionClass $class,
        LoggerInterface|null $logger = null,
    ): void {
        $cacheKey = $class->getName();

        if (isset($this->validatedClasses[$cacheKey])) {
            return;
        }

        $this->validatedClasses[$cacheKey] = true;

        $errors = [];

        if ($this->classHasSharedAmongstTranslations($class) && $this->classHasEmptyOnTranslate($class)) {
            $errors[] = new ClassLevelAttributeConflictException($class->getName());
        }

        foreach (ReflectionHelper::getHierarchyProperties($class) as $property) {
            try {
                $this->validateProperty($property);
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->getErrors());
            }
        }

        if ([] !== $errors) {
            foreach ($errors as $error) {
                $logger?->error('[TMI Translation][Embedded] '.$error->getMessage());
            }

            throw new ValidationException($errors);
        }
    }

    /**
     * Validates property attributes for conflicts.
     * Collects all errors before throwing ValidationException.
     * Results are cached per class::property.
     *
     * @throws ValidationException When validation errors are found
     */
    public function validateProperty(
        \ReflectionProperty $property,
        LoggerInterface|null $logger = null,
    ): void {
        $cacheKey = $property->class.'::$'.$property->name;

        if (isset($this->validatedProperties[$cacheKey])) {
            return;
        }

        $this->validatedProperties[$cacheKey] = true;

        $errors = $this->collectValidationErrors($property);

        if ([] !== $errors) {
            foreach ($errors as $error) {
                $logger?->error('[TMI Translation] '.$error->getMessage());
            }

            throw new ValidationException($errors);
        }
    }

    /**
     * Reads the #[Translatable] attribute from an entity class, if present.
     *
     * @param \ReflectionClass<object> $class
     */
    public function getTranslatableAttribute(\ReflectionClass $class): Translatable|null
    {
        $attrs = $class->getAttributes(Translatable::class, \ReflectionAttribute::IS_INSTANCEOF);
        if ([] === $attrs) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    /**
     * Generic attribute check with consistent configuration.
     */
    private function hasAttribute(\ReflectionProperty $property, string $attributeClass): bool
    {
        $cacheKey = $property->class.'::'.$property->name.'::'.$attributeClass;

        return $this->attributeCache[$cacheKey]
            ??= [] !== $property->getAttributes($attributeClass, \ReflectionAttribute::IS_INSTANCEOF);
    }

    /**
     * @return array<\LogicException>
     */
    private function collectValidationErrors(\ReflectionProperty $property): array
    {
        $errors = [];

        if ($this->isSharedAmongstTranslations($property) && $this->isEmptyOnTranslate($property)) {
            $errors[] = new AttributeConflictException(
                $property->class,
                $property->name,
                'SharedAmongstTranslations',
                'EmptyOnTranslate',
            );
        }

        if ($this->isEmptyOnTranslate($property) && $property->isReadOnly()) {
            $errors[] = new ReadonlyPropertyException(
                $property->class,
                $property->name,
            );
        }

        foreach ($this->collectTranslationRootErrors($property) as $error) {
            $errors[] = $error;
        }

        return $errors;
    }

    /**
     * The per-property half of the root contract (5.1). The per-class half -- at most
     * one root reference, the constructor rule for a non-nullable one -- needs the
     * whole class and lives in AttributeValidationPass.
     *
     * Every check is reflection-only. #[SharedAmongstTranslations] on a root reference
     * is tolerated as redundant: an application's transition carries it today and
     * there is nothing to migrate.
     *
     * @return list<TranslationRootContractException>
     */
    private function collectTranslationRootErrors(\ReflectionProperty $property): array
    {
        if (!$this->isTranslationRootReference($property)) {
            return $this->hasTranslationRootMarker($property)
                ? [TranslationRootContractException::forMarkerWithoutRoot($property->class, $property->name)]
                : [];
        }

        $errors = [];
        $type   = (string) $this->translationRootType($property);

        if (is_a($type, TranslatableInterface::class, true)) {
            $errors[] = TranslationRootContractException::forTranslatableRootType($property->class, $property->name, $type);
        }

        if ($this->isId($property)) {
            $errors[] = TranslationRootContractException::forIdRootProperty($property->class, $property->name);
        }

        if ($this->isEmptyOnTranslate($property)) {
            $errors[] = TranslationRootContractException::forEmptyOnTranslate($property->class, $property->name);
        }

        if (self::hasUniqueJoinColumn($property)) {
            $errors[] = TranslationRootContractException::forUniqueJoinColumn($property->class, $property->name);
        }

        return $errors;
    }

    private static function hasUniqueJoinColumn(\ReflectionProperty $property): bool
    {
        foreach ($property->getAttributes(ORM\JoinColumn::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            if (true === $attribute->newInstance()->unique) {
                return true;
            }
        }

        return false;
    }
}
