# Custom Handler Template

Use this template when creating a new translation handler. Replace placeholders with your implementation.

## Complete Handler Class

```php
<?php

declare(strict_types=1);

namespace App\Translation\Handler;

use Tmi\TranslationBundle\Translation\Context\PropertyTranslationContext;
use Tmi\TranslationBundle\Translation\Context\TranslationContext;
use Tmi\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * Handler for [FIELD_TYPE] fields.
 *
 * Purpose: [Describe why this handler exists and what field types it processes]
 *
 * Behavior:
 * - supports(): Returns true when [describe condition]
 * - translate(), $context->isShared(): [Describe sharing behavior]
 * - translate(), $context->isEmpty(): [Describe empty behavior]
 * - translate(), otherwise: [Describe cloning/transformation behavior]
 *
 * Priority: [XX] - Must run [before/after] [HandlerName] because [reason]
 */
final readonly class [HANDLER_NAME]Handler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper $attributeHelper,
        // TODO: Add other dependencies as needed
        // private EntityManagerInterface $entityManager,
        // private PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    /**
     * Determines if this handler can process the given context.
     *
     * TODO: Implement your matching condition
     * Common patterns:
     * - Narrow on PropertyTranslationContext (or EntityTranslationContext) first
     * - Check property type via reflection
     * - Check for specific attribute on property
     * - Check if the value is instance of specific class
     * - Check Doctrine metadata
     */
    public function supports(TranslationContext $context): bool
    {
        // Most custom handlers match a property value, not an entity -- narrow on
        // PropertyTranslationContext first. Use EntityTranslationContext instead if your
        // handler matches whole TranslatableInterface entities (like the bundle's own
        // TranslatableEntityHandler).
        if (!$context instanceof PropertyTranslationContext) {
            return false;
        }

        $property = $context->getProperty();
        if (null === $property) {
            return false;
        }

        // TODO: Replace with your condition
        // Example: Check if property has a specific attribute
        // return $property->getAttributes(YourAttribute::class) !== [];

        // Example: Check if the value is a specific type
        // return $context->getValue() instanceof YourValueObject;

        return false;
    }

    /**
     * Performs the translation for this context.
     *
     * $context->isShared() and $context->isEmpty() are pre-resolved by EntityTranslator
     * from the property's #[SharedAmongstTranslations]/#[EmptyOnTranslate] attributes
     * *before* this is called -- check them first, in that order, the same way
     * EntityTranslator itself picks between them.
     */
    public function translate(TranslationContext $context): mixed
    {
        \assert($context instanceof PropertyTranslationContext);
        $value = $context->getValue();

        if ($context->isShared()) {
            // TODO: Choose your sharing strategy

            // Option 1: Share the same instance (most common for value objects)
            return $value;

            // Option 2: Throw if sharing is not supported (for bidirectional relations)
            // throw new \RuntimeException(
            //     sprintf('SharedAmongstTranslations not supported for %s', $context->getProperty()?->getName())
            // );
        }

        if ($context->isEmpty()) {
            // TODO: Choose your empty strategy

            // Option 1: Return null (most common)
            return null;

            // Option 2: Return an empty instance
            // return new YourValueObject();

            // Option 3: Return an empty collection
            // return new ArrayCollection();
        }

        // TODO: Implement your translation logic

        // Simple clone (for value objects)
        // return clone $value;

        // Transform value (for locale-specific paths)
        // return $this->transformForLocale($value, $context->getTargetLocale());

        // Deep clone with processing
        // $clone = clone $value;
        // $this->processClone($clone, $context);
        // return $clone;

        return $value;
    }
}
```

## Service Registration

```yaml
# config/services.yaml
services:
    App\Translation\Handler\[HANDLER_NAME]Handler:
        arguments:
            # AttributeHelper is only registered under this id, not under its FQCN
            $attributeHelper: '@tmi_translation.utils.attribute_helper'
        tags:
            - { name: 'tmi_translation.translation_handler', priority: [PRIORITY] }
```

## Common Dependencies

| Dependency | When to Use |
|------------|-------------|
| `AttributeHelper` | Check Doctrine metadata, attributes on properties |
| `EntityManagerInterface` | Access Doctrine metadata, find related entities |
| `PropertyAccessorInterface` | Read/write properties dynamically |
| `EntityTranslatorInterface` | Recursively translate nested entities |

## TranslationContext Reference

Members shared by both context shapes, declared on the abstract `TranslationContext` base:

```php
$context->getSubject();          // mixed - the entity or value being translated, either shape
$context->getSourceLocale();     // string|null - Source locale code (e.g., 'en_US')
$context->getTargetLocale();     // string|null - Target locale code (e.g., 'de_DE')
$context->getTranslatedParent(); // object|null - Parent entity (for nested translation)
$context->getProperty();         // ?ReflectionProperty - Property being processed
$context->getCopySource();       // bool|null - Resolved copy_source for this entity
$context->isShared();            // bool - #[SharedAmongstTranslations] resolved by EntityTranslator
$context->isEmpty();             // bool - #[EmptyOnTranslate] resolved by EntityTranslator
```

Every getter above is nullable except `isShared()`/`isEmpty()` (default `false`) — narrow before
use, PHPStan runs at level max in this project and will reject an unchecked `string` assumption.

Two concrete subclasses add a typed accessor for the subject:

```php
// EntityTranslationContext -- the subject is a TranslatableInterface entity
$context->getEntity();  // TranslatableInterface

// PropertyTranslationContext -- the subject is a property's value
$context->getValue();   // mixed
```

`supports()` narrows with `instanceof EntityTranslationContext` / `instanceof
PropertyTranslationContext` before reading either typed accessor; `getSubject()` on the base
works either way when a handler is genuinely reachable with both shapes.

## What the context's subject holds

`supports()` must match the value's actual shape, not the entity that owns the property:

| Property kind | Context shape | Subject |
|---------------|----------------|---------|
| Scalar / `DateTime` | `PropertyTranslationContext` | the raw value (`getValue()`) |
| Embeddable | `PropertyTranslationContext` | the embeddable object (`getValue()`) |
| `ManyToOne` / `OneToOne` | `EntityTranslationContext` | the related entity (`getEntity()`) |
| `OneToMany` / `ManyToMany` | `PropertyTranslationContext` | the **`Collection`** (`getValue()`), never the owning entity |

Guarding a to-many handler with `instanceof EntityTranslationContext` makes `supports()` always
false, and the handler silently never runs — this was a real bug in the bundle's own collection
handlers until v3.0.0 (then expressed as `instanceof TranslatableInterface` against the old
`TranslationArgs` payload; the typed contexts in v4.0 make the same mistake a compile-time
`instanceof` check instead of a runtime data-shape guess).
