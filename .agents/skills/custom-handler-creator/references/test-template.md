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
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Context\EntityTranslationContext;
use Tmi\TranslationBundle\Translation\Context\PropertyTranslationContext;
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

        $context = $this->propertyContext($value, $property);

        // TODO: Configure mock expectations
        // $this->attributeHelper
        //     ->expects($this->once())
        //     ->method('someMethod')
        //     ->willReturn(true);

        // Act
        $result = $this->handler->supports($context);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_does_not_support_other_field_types(): void
    {
        // Arrange
        $value = 'regular string value';
        $property = new \ReflectionProperty(TestEntity::class, 'name');

        $context = $this->propertyContext($value, $property);

        // Act
        $result = $this->handler->supports($context);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function it_translates_[field_type]_correctly(): void
    {
        // Arrange
        $originalValue = /* TODO: Create original value */;
        $property = new \ReflectionProperty(TestEntity::class, '[fieldName]');

        $context = $this->propertyContext($originalValue, $property);

        // Act
        $result = $this->handler->translate($context);

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

        // isShared() is a fact EntityTranslator resolves and stamps onto the context
        // before calling translate() -- set it directly to exercise that branch in isolation.
        $context = $this->propertyContext($originalValue, $property)->setShared(true);

        // Act
        $result = $this->handler->translate($context);

        // Assert
        // For shared behavior (same instance):
        $this->assertSame($originalValue, $result);

        // OR for handlers that throw on shared:
        // $this->expectException(\RuntimeException::class);
        // $this->handler->translate($context);
    }

    #[Test]
    public function it_returns_null_when_empty(): void
    {
        // Arrange
        $originalValue = /* TODO: Create original value */;
        $property = new \ReflectionProperty(TestEntity::class, '[fieldName]');

        $context = $this->propertyContext($originalValue, $property)->setEmpty(true);

        // Act
        $result = $this->handler->translate($context);

        // Assert
        $this->assertNull($result);

        // OR for handlers that return an empty instance:
        // $this->assertInstanceOf(YourType::class, $result);
        // $this->assertTrue($result->isEmpty());
    }

    /**
     * A PropertyTranslationContext for a non-entity value (scalar, embeddable, Collection) --
     * the shape DoctrineObjectHandler::translateProperties() builds for a property. Mirrors
     * Tmi\TranslationBundle\Test\Translation\UnitTestCase::propertyContext() from the bundle's
     * own test suite (dev-only, not part of the published package -- copied here rather than
     * imported). A handler test living inside the bundle's own repository can extend
     * UnitTestCase directly and use its propertyContext()/entityContext() helpers instead of
     * duplicating them.
     */
    private function propertyContext(
        mixed $value,
        \ReflectionProperty|null $property = null,
        string $sourceLocale = 'en',
        string $targetLocale = 'fr',
    ): PropertyTranslationContext {
        $context = new PropertyTranslationContext($value, $sourceLocale, $targetLocale);
        if (null !== $property) {
            $context->setProperty($property);
        }

        return $context;
    }

    /**
     * An EntityTranslationContext for a TranslatableInterface entity -- the shape
     * EntityTranslator::translate() and the association handlers build. Only needed when your
     * handler's supports() narrows on EntityTranslationContext instead of
     * PropertyTranslationContext.
     */
    private function entityContext(
        TranslatableInterface $entity,
        \ReflectionProperty|null $property = null,
        string $sourceLocale = 'en',
        string $targetLocale = 'fr',
    ): EntityTranslationContext {
        $context = new EntityTranslationContext($entity, $sourceLocale, $targetLocale);
        if (null !== $property) {
            $context->setProperty($property);
        }

        return $context;
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
    $context = $this->propertyContext($original, $property);

    $result = $this->handler->translate($context);

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
    $context = $this->propertyContext($encryptedValue, $property);

    $this->encryptor->expects($this->once())
        ->method('decrypt')
        ->willReturn('plain value');

    $this->encryptor->expects($this->once())
        ->method('encrypt')
        ->willReturn('encrypted:def456');

    $result = $this->handler->translate($context);

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
    $context = $this->propertyContext($entity->getSlug(), $property)
        ->setTargetLocale('fr');

    $result = $this->handler->translate($context);

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
