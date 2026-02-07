<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Utils;

use Doctrine\ORM\Mapping as ORM;
use Psr\Log\LoggerInterface;
use Tmi\TranslationBundle\Doctrine\Attribute as TranslationAttribute;
use Tmi\TranslationBundle\Exception\AttributeConflictException;
use Tmi\TranslationBundle\Exception\ClassLevelAttributeConflictException;
use Tmi\TranslationBundle\Exception\ReadonlyPropertyException;
use Tmi\TranslationBundle\Exception\ValidationException;

class AttributeHelper
{
    private const array DOCTRINE_ATTRIBUTES = [
        'isEmbedded'   => ORM\Embedded::class,
        'isOneToOne'   => ORM\OneToOne::class,
        'isId'         => ORM\Id::class,
        'isManyToOne'  => ORM\ManyToOne::class,
        'isOneToMany'  => ORM\OneToMany::class,
        'isManyToMany' => ORM\ManyToMany::class,
    ];

    private const array TRANSLATION_ATTRIBUTES = [
        'isSharedAmongstTranslations' => TranslationAttribute\SharedAmongstTranslations::class,
        'isEmptyOnTranslate'          => TranslationAttribute\EmptyOnTranslate::class,
    ];
    /** @var array<string, true> */
    private array $validatedProperties = [];

    /** @var array<string, true> */
    private array $validatedClasses = [];

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

        foreach ($class->getProperties() as $property) {
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
     * Generic attribute check with consistent configuration.
     */
    private function hasAttribute(\ReflectionProperty $property, string $attributeClass): bool
    {
        return [] !== $property->getAttributes($attributeClass, \ReflectionAttribute::IS_INSTANCEOF);
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

        return $errors;
    }
}
