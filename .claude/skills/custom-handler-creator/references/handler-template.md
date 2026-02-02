# Custom Handler Template

Use this template when creating a new translation handler. Replace placeholders with your implementation.

## Complete Handler Class

```php
<?php

declare(strict_types=1);

namespace App\Translation\Handler;

use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * Handler for [FIELD_TYPE] fields.
 *
 * Purpose: [Describe why this handler exists and what field types it processes]
 *
 * Behavior:
 * - supports(): Returns true when [describe condition]
 * - translate(): [Describe cloning/transformation behavior]
 * - handleSharedAmongstTranslations(): [Describe sharing behavior]
 * - handleEmptyOnTranslate(): [Describe empty behavior]
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
     * Determines if this handler can process the given data.
     *
     * TODO: Implement your matching condition
     * Common patterns:
     * - Check property type via reflection
     * - Check for specific attribute on property
     * - Check if value is instance of specific class
     * - Check Doctrine metadata
     */
    public function supports(TranslationArgs $args): bool
    {
        $property = $args->getProperty();
        if (null === $property) {
            return false;
        }

        // TODO: Replace with your condition
        // Example: Check if property has a specific attribute
        // return $property->getAttributes(YourAttribute::class) !== [];

        // Example: Check if value is specific type
        // $value = $args->getDataToBeTranslated();
        // return $value instanceof YourValueObject;

        return false;
    }

    /**
     * Handles translation when #[SharedAmongstTranslations] is present.
     *
     * Options:
     * 1. Return original value unchanged (share same instance)
     * 2. Throw RuntimeException if sharing is not supported
     * 3. Clone and share (for immutable value objects)
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): mixed
    {
        // TODO: Choose your sharing strategy

        // Option 1: Share same instance (most common for value objects)
        return $args->getDataToBeTranslated();

        // Option 2: Throw if sharing is not supported (for bidirectional relations)
        // throw new \RuntimeException(
        //     sprintf('SharedAmongstTranslations not supported for %s', $args->getProperty()?->getName())
        // );
    }

    /**
     * Handles translation when #[EmptyOnTranslate] is present.
     *
     * Options:
     * 1. Return null (most common)
     * 2. Return empty instance of the type
     * 3. Return default value
     */
    public function handleEmptyOnTranslate(TranslationArgs $args): mixed
    {
        // TODO: Choose your empty strategy

        // Option 1: Return null (most common)
        return null;

        // Option 2: Return empty instance
        // return new YourValueObject();

        // Option 3: Return empty collection
        // return new ArrayCollection();
    }

    /**
     * Performs the actual translation/cloning of the value.
     *
     * This is called when neither SharedAmongstTranslations nor
     * EmptyOnTranslate attributes are present on the property.
     */
    public function translate(TranslationArgs $args): mixed
    {
        $value = $args->getDataToBeTranslated();

        // TODO: Implement your translation logic

        // Simple clone (for value objects)
        // return clone $value;

        // Transform value (for locale-specific paths)
        // return $this->transformForLocale($value, $args->getTargetLocale());

        // Deep clone with processing
        // $clone = clone $value;
        // $this->processClone($clone, $args);
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
            $attributeHelper: '@Tmi\TranslationBundle\Utils\AttributeHelper'
        tags:
            - { name: 'tmi_translation.handler', priority: [PRIORITY] }
```

## Common Dependencies

| Dependency | When to Use |
|------------|-------------|
| `AttributeHelper` | Check Doctrine metadata, attributes on properties |
| `EntityManagerInterface` | Access Doctrine metadata, find related entities |
| `PropertyAccessorInterface` | Read/write properties dynamically |
| `EntityTranslatorInterface` | Recursively translate nested entities |

## TranslationArgs Reference

```php
$args->getDataToBeTranslated();  // mixed - The value being translated
$args->getSourceLocale();        // string - Source locale code (e.g., 'en')
$args->getTargetLocale();        // string - Target locale code (e.g., 'fr')
$args->getTranslatedParent();    // ?object - Parent entity (for nested translation)
$args->getProperty();            // ?ReflectionProperty - Property being processed
```
