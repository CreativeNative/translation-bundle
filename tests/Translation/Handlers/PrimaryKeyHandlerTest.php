<?php

declare(strict_types=1);

namespace TMI\TranslationBundle\Test\Translation\Handlers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\Handlers\PrimaryKeyHandler;
use TMI\TranslationBundle\Utils\AttributeHelper;

#[CoversClass(PrimaryKeyHandler::class)]
final class PrimaryKeyHandlerTest extends TestCase
{
    private PrimaryKeyHandler $handler;
    private AttributeHelper $attributeHelper;
    public function setUp(): void
    {
        $this->attributeHelper = $this->createMock(AttributeHelper::class);
        $this->handler = new PrimaryKeyHandler($this->attributeHelper);
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsTrueWhenPropertyIsId(): void
    {
        $dummy = new class {
            public int $id = 1;
            public string $name = 'test';
        };

        $prop = new ReflectionProperty(get_class($dummy), 'id');
        $args = new TranslationArgs(123, 'en', 'de')->setProperty($prop);
        $this->attributeHelper->method('isId')->with($prop)->willReturn(true);
        self::assertTrue($this->handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsFalseWhenPropertyIsNotId(): void
    {
        $dummy = new class {
            public int $id = 1;
            public string $name = 'test';
        };

        $prop = new ReflectionProperty(get_class($dummy), 'name');
        $args = new TranslationArgs('foo', 'en', 'de')->setProperty($prop);
        $this->attributeHelper->method('isId')->with($prop)->willReturn(false);
        self::assertFalse($this->handler->supports($args));
    }

    public function testTranslateAlwaysReturnsNull(): void
    {
        $args = new TranslationArgs(123, 'en', 'de');
        self::assertNull($this->handler->translate($args), 'Primary keys must never be translated');
    }

    public function testHandleSharedAmongstTranslationsAlwaysReturnsNull(): void
    {
        $args = new TranslationArgs(123, 'en', 'de');
        self::assertNull($this->handler->handleSharedAmongstTranslations($args));
    }

    public function testHandleEmptyOnTranslateAlwaysReturnsNull(): void
    {
        $args = new TranslationArgs(123, 'en', 'de');
        self::assertNull($this->handler->handleEmptyOnTranslate($args));
    }
}
