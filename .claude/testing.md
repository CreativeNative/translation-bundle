# Testing Guidelines

## Framework

PHPUnit 12.4.4+ with 100% code coverage target.

## Test Structure

```
tests/
├── IntegrationTestCase.php      # Base class for DB tests
├── TestKernel.php               # Minimal test kernel (SQLite)
├── Fixtures/Entity/             # Test entities
├── Translation/Handlers/        # Handler unit tests
├── Doctrine/                    # ORM integration tests
└── DependencyInjection/         # Container tests
```

## Base Classes

### IntegrationTestCase

For tests requiring database access:

```php
class MyTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Test setup
    }
}
```

Uses in-memory SQLite database via TestKernel.

## Running Tests

```bash
docker exec php composer test                          # Full suite with coverage
docker exec php vendor/bin/phpunit                     # Without coverage
docker exec php vendor/bin/phpunit --filter MethodName # Single test
```

## Test Fixtures

Test entities live in `tests/Fixtures/Entity/`. Create specific fixtures for each relationship type being tested.

## Strict Mode

PHPUnit runs in strict mode — `failOnWarning`, `failOnNotice`, and `failOnRisky` are all enabled. No warnings, notices, or risky tests allowed.

## Assertions & Mocks

- Use `self::assertXxx()`, not `$this->assertXxx()`
- Use `createStub()` for objects without expectations
- Add `#[AllowMockObjectsWithoutExpectations]` when a mock has no expectations and `createStub()` is not suitable

## Coverage Requirements

- **Target**: 100% line coverage (enforced in CI)
- Coverage report: `var/clover.xml`

## Writing Tests

### Handler Tests

Test each handler's `supports()`, `translate()`, `handleSharedAmongstTranslations()`, and `handleEmptyOnTranslate()` methods:

```php
public function testSupportsReturnsTrueForValidValue(): void
{
    $handler = new ScalarHandler();
    $args = $this->createTranslationArgs();

    self::assertTrue($handler->supports('value', $args));
}
```

### Integration Tests

Test full translation flow through EntityTranslator:

```php
public function testTranslateCreatesNewEntityWithCorrectLocale(): void
{
    $entity = $this->createTestEntity('en_US');
    $translated = $this->translator->translate($entity, 'de_DE');

    self::assertSame('de_DE', $translated->getLocale());
    self::assertSame($entity->getTuuid(), $translated->getTuuid());
}
```

## CI Pipeline

Tests run in GitHub Actions with:
1. PHPUnit with xdebug coverage
2. Coverage upload to Codecov
3. Must pass for merge
