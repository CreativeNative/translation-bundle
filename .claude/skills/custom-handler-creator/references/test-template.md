# Handler Test Template

Use this template to create PHPUnit tests for custom translation handlers.

## Complete Test Class

```php
<?php

declare(strict_types=1);

namespace App\Tests\Translation\Handler;

use App\Translation\Handler\[HANDLER_NAME]Handler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Utils\AttributeHelper;

#[CoversClass([HANDLER_NAME]Handler::class)]
final class [HANDLER_NAME]HandlerTest extends TestCase
{
    private [HANDLER_NAME]Handler $handler;
    private MockObject&AttributeHelper $attributeHelper;

    protected function setUp(): void
    {
        $this->attributeHelper = $this->createMock(AttributeHelper::class);
        $this->handler = new [HANDLER_NAME]Handler($this->attributeHelper);
    }

    #[Test]
    public function it_supports_[field_type](): void
    {
        // Arrange
        $value = /* TODO: Create instance of your field type */;
        $property = new \ReflectionProperty(TestEntity::class, '[fieldName]');

        $args = $this->createTranslationArgs($value, $property);

        // TODO: Configure mock expectations
        // $this->attributeHelper
        //     ->expects($this->once())
        //     ->method('someMethod')
        //     ->willReturn(true);

        // Act
        $result = $this->handler->supports($args);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_does_not_support_other_field_types(): void
    {
        // Arrange
        $value = 'regular string value';
        $property = new \ReflectionProperty(TestEntity::class, 'name');

        $args = $this->createTranslationArgs($value, $property);

        // Act
        $result = $this->handler->supports($args);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function it_translates_[field_type]_correctly(): void
    {
        // Arrange
        $originalValue = /* TODO: Create original value */;
        $property = new \ReflectionProperty(TestEntity::class, '[fieldName]');

        $args = $this->createTranslationArgs($originalValue, $property);

        // Act
        $result = $this->handler->translate($args);

        // Assert
        // TODO: Add assertions for your translation behavior
        // $this->assertInstanceOf(YourType::class, $result);
        // $this->assertNotSame($originalValue, $result); // Verify clone
        // $this->assertEquals($expectedValue, $result->getValue());
    }

    #[Test]
    public function it_shares_when_marked_shared(): void
    {
        // Arrange
        $originalValue = /* TODO: Create original value */;
        $property = new \ReflectionProperty(TestEntity::class, '[fieldName]');

        $args = $this->createTranslationArgs($originalValue, $property);

        // Act
        $result = $this->handler->handleSharedAmongstTranslations($args);

        // Assert
        // For shared behavior (same instance):
        $this->assertSame($originalValue, $result);

        // OR for handlers that throw on shared:
        // $this->expectException(\RuntimeException::class);
        // $this->handler->handleSharedAmongstTranslations($args);
    }

    #[Test]
    public function it_returns_null_when_empty(): void
    {
        // Arrange
        $originalValue = /* TODO: Create original value */;
        $property = new \ReflectionProperty(TestEntity::class, '[fieldName]');

        $args = $this->createTranslationArgs($originalValue, $property);

        // Act
        $result = $this->handler->handleEmptyOnTranslate($args);

        // Assert
        $this->assertNull($result);

        // OR for handlers that return empty instance:
        // $this->assertInstanceOf(YourType::class, $result);
        // $this->assertTrue($result->isEmpty());
    }

    private function createTranslationArgs(
        mixed $data,
        ?\ReflectionProperty $property = null,
        string $sourceLocale = 'en',
        string $targetLocale = 'fr',
    ): TranslationArgs {
        return new TranslationArgs(
            dataToBeTranslated: $data,
            sourceLocale: $sourceLocale,
            targetLocale: $targetLocale,
            translatedParent: null,
            property: $property,
        );
    }
}

// TODO: Create test entity for reflection
class TestEntity
{
    private mixed $[fieldName];
    private string $name;
}
```

## Test Patterns by Handler Behavior

### Value Object Handler Tests

```php
#[Test]
public function it_clones_value_object_on_translate(): void
{
    $original = new Money(100, 'EUR');
    $args = $this->createTranslationArgs($original, $property);

    $result = $this->handler->translate($args);

    $this->assertInstanceOf(Money::class, $result);
    $this->assertNotSame($original, $result);
    $this->assertEquals(100, $result->getAmount());
}
```

### Encrypted Field Handler Tests

```php
#[Test]
public function it_decrypts_and_re_encrypts_on_translate(): void
{
    $encryptedValue = 'encrypted:abc123';
    $args = $this->createTranslationArgs($encryptedValue, $property);

    $this->encryptor->expects($this->once())
        ->method('decrypt')
        ->willReturn('plain value');

    $this->encryptor->expects($this->once())
        ->method('encrypt')
        ->willReturn('encrypted:def456');

    $result = $this->handler->translate($args);

    $this->assertEquals('encrypted:def456', $result);
}
```

### Computed Property Handler Tests

```php
#[Test]
public function it_recalculates_computed_value_for_target_locale(): void
{
    $entity = new Product();
    $entity->setName('Widget');
    $args = $this->createTranslationArgs($entity->getSlug(), $property);
    $args = $args->withTargetLocale('fr');

    $result = $this->handler->translate($args);

    // Computed value should be recalculated, not copied
    $this->assertNull($result); // or new computed value
}
```

## Arrange/Act/Assert Pattern

Each test follows the AAA pattern:

1. **Arrange**: Set up test data, mocks, and expectations
2. **Act**: Call the handler method being tested
3. **Assert**: Verify the result matches expectations

## Running Tests

```bash
# Run only handler tests
./vendor/bin/phpunit tests/Translation/Handler/

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage tests/Translation/Handler/
```
