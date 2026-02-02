# Testing Patterns

**Analysis Date:** 2026-02-02

## Test Framework

**Runner:**
- **PHPUnit** version ^12.4.4
- Config: `phpunit.xml`
- Command: `vendor/bin/phpunit`

**Assertion Library:**
- PHPUnit built-in assertions via `TestCase::assert*()` methods
- Static assertions via `self::assert*()` pattern

**Run Commands:**
```bash
composer test              # Run all tests with XDEBUG_MODE=coverage
vendor/bin/phpunit        # Direct test execution
vendor/bin/phpunit --filter="testName"  # Run specific test
composer check             # Runs: test, cs-check, stan-min (full quality gate)
```

## Test File Organization

**Location:**
- Co-located in `tests/` directory mirroring `src/` structure
- Test namespace mirrors source: `src/Translation/EntityTranslator.php` → `tests/Translation/EntityTranslatorTest.php`
- Namespace: `Tmi\TranslationBundle\Test\...` (with `Test` prefix in namespace)

**Naming:**
- Test file suffix: `Test` (e.g., `EntityTranslatorTest.php`, `TranslatableEventSubscriberTest.php`)
- Test class suffix: `Test` (e.g., `class EntityTranslatorTest extends UnitTestCase`)
- Integration tests distinguished by base class: extend `IntegrationTestCase` vs `UnitTestCase`

**Structure:**
```
tests/
├── DependencyInjection/
│   ├── Compiler/
│   │   └── TranslationHandlerPassTest.php
│   └── TmiTranslationExtensionTest.php
├── Doctrine/
│   ├── Attribute/
│   ├── EventSubscriber/
│   ├── Filter/
│   ├── Model/
│   └── Type/
├── Translation/
│   ├── Handlers/
│   ├── UnitTestCase.php
│   └── EntityTranslatorTest.php
├── Fixtures/
│   └── Entity/  (Test data entities)
├── IntegrationTestCase.php
└── TestKernel.php
```

## Test Structure

**Suite Organization:**
```php
<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation;

use PHPUnit\Framework\Attributes\CoversClass;
use Tmi\TranslationBundle\Translation\EntityTranslator;

#[CoversClass(EntityTranslator::class)]
final class EntityTranslatorTest extends UnitTestCase
{
    public function testProcessTranslationThrowsWhenLocaleIsNotAllowed(): void
    {
        $entity = new Scalar();
        $entity->setLocale('en_US');

        $args = new TranslationArgs($entity, 'en_US', 'xx');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Locale "xx" is not allowed. Allowed locales:');

        $this->translator->processTranslation($args);
    }

    public function testReturnsFallbackWhenNoHandlerSupports(): void
    {
        // Arrange
        $args = $this->getTranslationArgs(null, 'fallback');
        $this->translator->addTranslationHandler($this->handlerNotSupporting());

        // Act
        $result = $this->translator->processTranslation($args);

        // Assert
        self::assertSame('fallback', $result);
    }
}
```

**Patterns:**

1. **PHPUnit Attributes** (not annotations):
   - `#[CoversClass(ClassName::class)]` on test class (PHPUnit 10+ format)
   - Maps tests to covered source classes for coverage metrics

2. **Method Naming:**
   - `public function testDescriptiveActionExpectation(): void`
   - All test methods return `void`
   - Examples: `testProcessTranslationThrowsWhenLocaleIsNotAllowed()`, `testFirstSupportingHandlerWins()`

3. **Setup/Teardown:**
   - `setUp(): void` inherited from base class (called before each test)
   - `tearDown(): void` defined in `IntegrationTestCase` for cleanup

4. **Assertion Style:**
   - Static assertions: `self::assertSame()`, `self::assertInstanceOf()`, `self::assertTrue()`
   - Non-static style: `$this->assertTrue()` (both used interchangeably)
   - Never assert for `self::fail()` in exception tests; use `expectException()` instead

## Mocking

**Framework:**
- PHPUnit built-in mocking via `TestCase::createMock()`, `getMockBuilder()`
- No external mocking library (Mockery, Prophecy)

**Patterns:**
```php
// Simple mock creation
$entityManager = $this->createMock(EntityManagerInterface::class);

// Mock builder for partial mocking/method configuration
$qbMock = $this->getMockBuilder(QueryBuilder::class)
    ->disableOriginalConstructor()
    ->onlyMethods(['select', 'from', 'where', 'setParameter', 'getQuery'])
    ->getMock();

// Configurable mock expectations
$handler = $this->createMock(TranslationHandlerInterface::class);
$handler->expects($this->once())->method('translate')->with($args)->willReturn('result');
$handler->method('supports')->willReturn(true);  // Stub without expectation

// Callback expectations
$handler->method('supports')->willReturnCallback(
    fn ($property) => false
);
```

**What to Mock:**
- External dependencies: `EntityManagerInterface`, `EventDispatcherInterface`, `AttributeHelper`
- Database queries: Mock `QueryBuilder` and `Query` to prevent actual DB access
- Event dispatchers: Always mock to avoid side effects
- Doctrine mappings: Mock `ClassMetadata` for relation handling tests

**What NOT to Mock:**
- Value objects: Create real instances (e.g., `Tuuid`, `TranslationArgs`, `Scalar` entities)
- Utility classes: Create real instances (e.g., `PropertyAccessor`, `AttributeHelper` in integration tests)
- Domain logic: Use real implementations when testing behavior (not just mocks)

## Fixtures and Factories

**Test Data:**
- Located in `tests/Fixtures/Entity/` directory
- Real entity classes implementing `TranslatableInterface`
- Examples:
  - `Scalar` - simple translatable entity with scalar properties
  - `Address` - embedded value object
  - `TranslatableManyToManyBidirectionalParent` - complex relations

**Example Fixture from `tests/Fixtures/Entity/Scalar/Scalar.php`:**
```php
final class Scalar implements TranslatableInterface
{
    use TranslatableTrait;

    private string $title = '';
    private string|null $body = null;
    private int|null $number = null;

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
}
```

**Factory Methods in Test Base Classes:**
- `UnitTestCase::getTranslationArgs()` - Creates `TranslationArgs` with default values
- `UnitTestCase::getTranslator()` - Builds configured `EntityTranslator` with mocks
- `IntegrationTestCase::assertIsTranslation()` - Asserts translation correctness (tuuid, locale, cloning)

## Coverage

**Requirements:**
- Coverage enabled: `phpunit.xml` has `<coverage>` section
- HTML report: `var/coverage-html/`
- Clover report: `var/clover.xml`

**View Coverage:**
```bash
vendor/bin/phpunit --coverage-html=var/coverage-html
# Open var/coverage-html/index.html in browser
```

**Coverage Settings:**
- Ignores deprecated code units: `ignoreDeprecatedCodeUnits="true"`
- Coverage only for `src/` directory (not tests, var, vendor)

**Test Count:** 218 public test methods across 54 test files (~4 tests per file average)

## Test Types

**Unit Tests:**
- Scope: Single class in isolation with mocked dependencies
- Base class: `UnitTestCase extends TestCase`
- Example: `EntityTranslatorTest`, `TranslatableEventSubscriberTest`
- Setup: Creates mocks in `setUp()` for `EntityManager`, `EventDispatcher`, `AttributeHelper`
- Approach: Behavior-driven with method expectations

**Integration Tests:**
- Scope: Full Symfony kernel + Doctrine ORM with real database (SQLite)
- Base class: `IntegrationTestCase extends KernelTestCase`
- Example: `TranslatableEventSubscriberIntegrationTest`, `EmbeddedTranslationTest`
- Setup:
  - Boots kernel: `self::bootKernel()`
  - Registers Doctrine types: `Type::addType(TuuidType::NAME, TuuidType::class)`
  - Creates schema: `SchemaTool` creates/drops tables
  - Registers event subscriber: `TranslatableEventSubscriber`
- Approach: Full end-to-end testing with real persistence

**E2E Tests:**
- Not used in this codebase
- Integration tests serve as functional/E2E tests

## Common Patterns

**Async Testing:**
- Not applicable (no async operations in PHP codebase)

**Error Testing:**
```php
public function testProcessTranslationThrowsWhenLocaleIsNotAllowed(): void
{
    $entity = new Scalar();
    $entity->setLocale('en_US');

    $args = new TranslationArgs($entity, 'en_US', 'xx');

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Locale "xx" is not allowed. Allowed locales:');

    $this->translator->processTranslation($args);
}
```

**Testing with Reflection (Private Properties):**
```php
public function testProcessTranslationUsesExistingCacheEntry(): void
{
    $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

    // Inject into private translationCache via reflection
    $rp = new \ReflectionProperty($this->translator, 'translationCache');
    $rp->setValue($this->translator, [(string) $tuuid => ['de_DE' => $cached]]);

    $args = new TranslationArgs($entity, 'en_US', 'de_DE');
    $result = $this->translator->processTranslation($args);

    self::assertSame($cached, $result);
}
```

**Testing Specific Handler Behavior:**
```php
public function testSharedAmongstTranslationsBranchCallsDedicatedHandler(): void
{
    $propClass = new class {
        public string|null $title = null;
    };
    $prop = new \ReflectionProperty($propClass, 'title');
    $args = $this->getTranslationArgs($prop);

    $this->attributeHelper->method('isSharedAmongstTranslations')->with($prop)->willReturn(true);
    $this->attributeHelper->method('isEmptyOnTranslate')->with($prop)->willReturn(false);

    $handler = $this->handlerSupporting(
        $args,
        'unused',
        null,
        ['handleSharedAmongstTranslations' => 'shared-result'],
    );

    $this->translator->addTranslationHandler($handler);
    self::assertSame('shared-result', $this->translator->processTranslation($args));
}
```

**Testing Database Interactions (Integration):**
```php
public function testPrePersistGeneratesTuuidForTranslatableEntities(): void
{
    $entity = new Scalar();
    $this->assertInstanceOf(Tuuid::class, $entity->getTuuid());

    $args = new PrePersistEventArgs($entity, $this->entityManager);

    $this->subscriber->prePersist($args);

    $this->assertNotNull($entity->getTuuid()->getValue());
    $this->assertTrue(Uuid::isValid($entity->getTuuid()->getValue()));
}
```

**Using Callback Matchers:**
```php
$handler->method('supports')->willReturnCallback(
    fn ($property) => false
);

// Or with static callback
$handler->method('supports')->with(
    self::callback(static fn (TranslationArgs $args) => $args->getSourceLocale() === 'en_US')
)->willReturn(true);
```

## PHPUnit Configuration Details

**From `phpunit.xml`:**
- Bootstrap: `vendor/autoload.php`
- Cache directory: `var/cache/phpunit`
- Execution order: `depends,defects` (respects `@depends` annotations first)
- Colors: Enabled
- Strict settings:
  - `failOnDeprecation="true"` - Fail on deprecated code usage
  - `failOnNotice="true"` - Fail on PHP notices
  - `failOnWarning="true"` - Fail on PHP warnings
  - `failOnRisky="true"` - Fail on risky tests (e.g., tests with no assertions)
  - `beStrictAboutOutputDuringTests="true"` - No unexpected output

**Test Kernel:**
- Server: `KERNEL_CLASS=Tmi\TranslationBundle\Test\TestKernel`
- Environment: `APP_ENV=test`
- Deprecation handling: `SYMFONY_DEPRECATIONS_HELPER=weak_vendors`
- Error reporting: Maximum (`E_ALL`)

---

*Testing analysis: 2026-02-02*
