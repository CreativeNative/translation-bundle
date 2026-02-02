# Coding Conventions

**Analysis Date:** 2026-02-02

## Naming Patterns

**Files:**
- Classes: PascalCase (e.g., `EntityTranslator.php`, `TranslatableInterface.php`)
- All files are `.php` extension
- No abbreviated names—full descriptive names preferred

**Functions/Methods:**
- camelCase for all public, protected, and private methods
- Test methods: `testDescriptiveActionExpectation` (e.g., `testProcessTranslationThrowsWhenLocaleIsNotAllowed`)
- Static factory methods: `generate()`, `create()` (e.g., `Tuuid::generate()`)
- Private helper methods prefixed with context (e.g., `handlerSupporting()`, `handlerNotSupporting()`)

**Variables:**
- camelCase for all local variables, properties, and parameters
- Meaningful names preferred (e.g., `$translationCache`, `$attributeHelper`, not `$cache` or `$helper`)
- Array parameters often named with plural form (e.g., `$handlers`, `$locales`)

**Types/Classes:**
- PascalCase for all class, interface, trait, enum names
- Value objects: use `readonly` modifier and final classes (e.g., `final readonly class Tuuid`)
- Handler classes: suffix with `Handler` (e.g., `BidirectionalManyToOneHandler`)
- Interfaces: suffix with `Interface` (e.g., `EntityTranslatorInterface`, `TranslatableInterface`)
- Attributes: placed in `Attribute/` directory (e.g., `EmptyOnTranslate`, `SharedAmongstTranslations`)

**Constants:**
- UPPER_SNAKE_CASE for constants
- Type declaration required: `private const array DOCTRINE_ATTRIBUTES = [...]`
- Prefixed with visibility modifier and typed: `public const string NAME = 'tuuid'`

## Code Style

**Formatting:**
- Tool: **PHP CS Fixer** (version @stable)
- Config: `.php-cs-fixer.dist.php`
- PSR-12 standard enforced as base
- Symfony standard enforced via `@Symfony` ruleset

**Key PHP-CS-Fixer Rules:**
- Short array syntax: `[]` not `array()`
- Nullable type declaration syntax: union types `string|null` preferred over legacy `?string`
- Ordered imports: alphabetical sort (`ordered_imports` with `alpha` algorithm)
- Ordered class elements: properties before methods
- Single quotes required: `'string'` not `"string"`
- Binary operator alignment: `align_single_space_minimal`
- Trailing commas in multiline: arrays, arguments, parameters
- No superfluous phpdoc tags
- No blank lines after class opening
- Strict parameter typing enabled (`strict_param`)

**Linting:**
- Tool: **PHPStan** (version ^2.1.32)
- Config: `phpstan.neon`
- Level: minimum 1 enforced via `composer check` script
- Levels 2 (next) and max available for stricter validation
- Strict rules enabled: loose comparison disallowed, boolean conditions checked, empty() disallowed, no variable variables
- Dynamic property access forbidden

**Strict Type Declaration:**
- `declare(strict_types=1);` required at top of every PHP file (enforced by PHP-CS-Fixer)
- All parameters and return types must be typed

## Import Organization

**Order:**
1. Declare statement: `declare(strict_types=1);`
2. Namespace declaration: `namespace Tmi\TranslationBundle\...;`
3. Blank line
4. Use statements: alphabetically ordered
5. Blank line
6. Class/interface/trait definition

**Path Aliases:**
- No custom aliases defined
- Full namespace paths used throughout
- Symfony autowiring attributes used: `#[Autowire(param: '...')]`

**Example from `EntityTranslator.php`:**
```php
<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Event\TranslateEvent;
// ... more imports in alphabetical order
```

## Error Handling

**Patterns:**
- Custom exceptions not used; standard SPL exceptions preferred
- `\LogicException` thrown for validation failures with descriptive messages
- `\ErrorException` thrown for semantic violations in handlers
- `\InvalidArgumentException` thrown for invalid arguments (e.g., invalid Tuuid value)
- Exception messages include context (e.g., class name, property name, actual values)
- Example: `sprintf('Locale "%s" is not allowed. Allowed locales: %s', $locale, implode(', ', $this->locales))`

## Logging

**Framework:** None defined
- Logging handled through Symfony/Doctrine event dispatchers
- `EventDispatcherInterface` used to trigger `TranslateEvent` for logging hooks
- No direct `logger` service usage in codebase

## Comments

**When to Comment:**
- Class-level docblocks: Always (public classes, interfaces)
- Method-level docblocks: When method purpose is not obvious from signature
- Complex logic: Comments for non-obvious algorithm decisions
- GitHub issue references: Included in docblocks (e.g., `See GitHub Issue: https://github.com/CreativeNative/translation-bundle/issues/3`)

**JSDoc/PHPDoc Style:**
- Format: PHPDoc blocks with tags `@param`, `@return`, `@throws`
- Type hints in docblocks match PHP type declarations
- Complex array types documented: `@param array<string, array<string, TranslatableInterface>> $cache`
- Parameter description on same line as tag: `@param TranslationArgs $args contains the entity or property to translate`
- Example from `EntityTranslator.php`:
```php
/**
 * Process translation for a given entity or property.
 *
 * This method handles:
 *  - Top-level entity translation
 *  - Properties with #[SharedAmongstTranslations] or #[EmptyOnTranslate]
 *  - Embedded properties that may contain shared or empty attributes internally
 *
 * @param TranslationArgs $args contains the entity or property to translate, source/target locales, and parent entity
 *
 * @return mixed Translated entity, embedded, or property value according to attribute rules
 */
```

## Function Design

**Size:**
- No explicit size limit enforced
- Average method size: 10-30 lines for handlers, up to 60+ for complex methods
- Multiple smaller methods preferred over one large method

**Parameters:**
- Value objects used to bundle related parameters (e.g., `TranslationArgs` groups entity, locales, property, parent)
- Constructor injection preferred over method parameters for dependencies
- No optional parameters in signatures; use proper method overloading or separate methods

**Return Values:**
- Explicit return types required
- Union types used for multiple possible returns: `TranslatableInterface|null`, `mixed`
- `mixed` used when return type varies significantly by context

## Module Design

**Exports:**
- All public classes/interfaces exported for external use
- Strict visibility enforcement: only public methods/properties exposed
- Private/protected methods clearly indicate internal use
- No barrel files (index.php re-exports) found; each file directly imported

**Architecture Patterns:**
- Handlers pattern: `TranslationHandlerInterface` with implementing handlers for different data types
- Strategy pattern: Multiple handlers support different entity/property combinations
- Dependency injection: Constructor-based, Symfony autowiring via `#[Autowire]`
- Event pattern: `EventDispatcherInterface` for `TranslateEvent` (pre/post translation)
- Value objects: Immutable `final readonly` classes (e.g., `Tuuid`)

**Example Handler Pattern from `BidirectionalManyToOneHandler.php`:**
```php
final readonly class BidirectionalManyToOneHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper $attributeHelper,
        private EntityManagerInterface $entityManager,
        private PropertyAccessorInterface $propertyAccessor,
        private EntityTranslatorInterface $translator,
    ) {
    }

    public function supports(TranslationArgs $args): bool
    {
        // Check if this handler can process the given translation args
    }

    public function translate(TranslationArgs $args): mixed
    {
        // Perform translation
    }

    public function handleSharedAmongstTranslations(TranslationArgs $args): mixed
    {
        // Handle special attribute
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): mixed
    {
        // Handle special attribute
    }
}
```

---

*Convention analysis: 2026-02-02*
