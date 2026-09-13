# Code Style & PHP Guidelines

## PHP Version

PHP 8.4+ with strict types enabled in all files:

```php
<?php

declare(strict_types=1);
```

## Type Declarations

- Full type hints on all parameters and return types
- Use union types and nullable types appropriately
- Prefer readonly properties where applicable

```php
public function translate(
    TranslatableInterface $entity,
    string $locale
): TranslatableInterface
```

## Naming Conventions

| Element | Convention | Example |
|---------|------------|---------|
| Interface | Suffix with `Interface` | `TranslatableInterface` |
| Trait | Suffix with `Trait` | `TranslatableTrait` |
| Handler | Suffix with `Handler` | `ScalarHandler` |
| Event | Suffix with `Event` | `TranslateEvent` |
| Exception | Descriptive name | `LogicException`, `RuntimeException` |

## Class Structure

1. Constants
2. Properties (injected dependencies first)
3. Constructor
4. Public methods
5. Protected methods
6. Private methods

## Attributes Over Annotations

Use PHP 8 attributes exclusively:

```php
// Correct
#[ORM\Column(type: 'string', length: 255)]
private string $name;

// Avoid - old annotation style
/** @ORM\Column(type="string", length=255) */
```

## Service Configuration

Use constructor injection with autowiring:

```php
public function __construct(
    private readonly EntityManagerInterface $entityManager,
    #[Autowire(param: 'kernel.enabled_locales')]
    private readonly array $locales,
)
```

## Error Handling

| Exception | Use Case |
|-----------|----------|
| `LogicException` | Invalid configuration |
| `RuntimeException` | Runtime failures |
| `InvalidArgumentException` | Invalid input values |

Bundle exceptions live in `src/Exception/` and follow one idiom: a `final` class extending the
SPL base above, **named static factories** (`forSharedAndEmpty()`, `forAssociation()`,
`fromMessages()`) instead of public constructors with getters, and a one-paragraph message that
names class and property and ends in a `Solution:` sentence. The message is the contract; nothing
reads structured fields off an exception.

| Bundle exception | Base | Raised by |
|---|---|---|
| `ValidationException` | `LogicException` | `AttributeHelper` (aggregate of the errors below), compiler passes via `fromMessages()` |
| `AttributeConflictException`, `ClassLevelAttributeConflictException`, `ReadonlyPropertyException`, `EmptyOnTranslateTypeException`, `TranslationRootContractException` | `LogicException` | attribute validation, compile time and translate time |
| `SharedAssociationException` | `RuntimeException` | every handler that meets `#[SharedAmongstTranslations]` on an association to a translatable entity |
| `OrphanTranslationException`, `SharedValueConflictException`, `RootAdoptionException` | `RuntimeException` / `LogicException` | flush-time listeners and `adopt-root` |

## Code Quality Tools

- **Pre-commit**: Run `docker exec php composer check` (cs-fix + stan + test) before every commit
- **PHP-CS-Fixer**: Coding standards, runs automatically via `composer check`
- **PHPStan**: Level max, 0 errors required, runs automatically via `composer check`
- **PHPUnit**: 100% line coverage required, runs automatically via `composer check`
