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
├── DependencyInjection/         # Container tests
├── Performance/                 # Query-budget tests (QueryBudgetTest.php)
└── Support/                     # Test-only infrastructure (QueryCounter.php)
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

    self::assertTrue($handler->supports($args));
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

### Query-Budget Tests (v4.0)

`tests/Performance/QueryBudgetTest.php` (`IntegrationTestCase`) asserts an exact database
round-trip count for a documented operation — `assertSame`, never a ceiling (`assertLessThan`
lets a budget silently regress; `assertSame` fails the moment it does):

```php
public function testTranslateEntityWithExistingVariantIsOneQueryAndNoInserts(): void
{
    // ... seed a de_DE variant, reset the counter ...
    $this->translator->translate($product, 'de_DE');

    self::assertSame(1, $this->queryCounter->count());
}
```

`tests/Support/QueryCounter.php` is a PSR-3 logger wired behind DBAL's own logging middleware
in `TestKernel` (test env only); it counts one message per executed statement and
deliberately excludes transaction-control messages (`beginTransaction`/`commit`/`rollBack`),
so `flush()`'s implicit transaction never inflates a budget. Every number in README.md §
Performance and llms.md § Performance traces back to one of these assertions — changing a
number in either doc without a corresponding test change is a discrepancy the reviewer should
flag.

### Negative-Proof Discipline

Every bug-fix commit in this codebase carries a test that is demonstrably **red against the
old code**, not merely green after the fix — proven either by `git stash`-ing the `src/`
change and re-running the new test (documented in the commit body), or by the test itself
asserting the specific wrong behaviour the old code produced (e.g. a second row with a new
id, not a generic exception) rather than a vague "it doesn't crash" check. This is a
reviewable claim, not a convention taken on faith — a reviewer checking a bug-fix PR asks for
the red run, the same way `composer check` is asked for the green one.

## CI Pipeline

Tests run in GitHub Actions with:
1. PHPUnit with xdebug coverage
2. Coverage upload to Codecov
3. Must pass for merge
