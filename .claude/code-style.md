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
    #[Autowire(param: 'tmi_translation.locales')]
    private readonly array $locales,
)
```

## Error Handling

| Exception | Use Case |
|-----------|----------|
| `LogicException` | Invalid configuration |
| `RuntimeException` | Runtime failures |
| `InvalidArgumentException` | Invalid input values |

## Code Quality Tools

- **Pre-commit**: Run `docker exec php composer check` (cs-fix + stan + test) before every commit
- **PHP-CS-Fixer**: Coding standards, runs automatically via `composer check`
- **PHPStan**: Level max, 0 errors required, runs automatically via `composer check`
- **PHPUnit**: 100% line coverage required, runs automatically via `composer check`
