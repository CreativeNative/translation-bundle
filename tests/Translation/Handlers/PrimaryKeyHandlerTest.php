<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Handlers\PrimaryKeyHandler;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(PrimaryKeyHandler::class)]
final class PrimaryKeyHandlerTest extends UnitTestCase
{
    private PrimaryKeyHandler $handler;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        $this->handler = new PrimaryKeyHandler($this->attributeHelper());
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsTrueWhenPropertyIsId(): void
    {
        $dummy = new class {
            public int $id      = 1;
            public string $name = 'test';
        };

        $prop    = new \ReflectionProperty($dummy::class, 'id');
        $context = $this->propertyContext(123, $prop);
        $this->attributeHelper()->method('isId')->with($prop)->willReturn(true);
        self::assertTrue($this->handler->supports($context));
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseWhenPropertyIsNotId(): void
    {
        $dummy = new class {
            public int $id      = 1;
            public string $name = 'test';
        };

        $prop    = new \ReflectionProperty($dummy::class, 'name');
        $context = $this->propertyContext('foo', $prop);
        $this->attributeHelper()->method('isId')->with($prop)->willReturn(false);
        self::assertFalse($this->handler->supports($context));
    }

    public function testSupportsReturnsFalseWhenNoPropertySet(): void
    {
        $context = $this->propertyContext(123);
        self::assertFalse($this->handler->supports($context));
    }

    /**
     * translate() always returns null by design -- shared/empty resolutions collapse
     * into the same method and behave identically, since a primary key is never
     * translated, shared, nor emptied.
     */
    public function testTranslateAlwaysReturnsNull(): void
    {
        $context = $this->propertyContext(123);
        $result  = $this->handler->translate($context);
        self::assertThat($result, self::isNull(), 'Primary keys must never be translated');
    }

    public function testTranslateAlwaysReturnsNullWhenShared(): void
    {
        $context = $this->propertyContext(123)->setShared(true);
        self::assertThat($this->handler->translate($context), self::isNull());
    }

    public function testTranslateAlwaysReturnsNullWhenEmpty(): void
    {
        $context = $this->propertyContext(123)->setEmpty(true);
        self::assertThat($this->handler->translate($context), self::isNull());
    }
}
