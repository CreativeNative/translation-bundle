<?php

declare(strict_types=1);

namespace TMI\TranslationBundle\Test\Translation\Handlers;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\Handlers\PrimaryKeyHandler;
use TMI\TranslationBundle\Utils\AttributeHelper;

#[\PHPUnit\Framework\Attributes\CoversClass(\TMI\TranslationBundle\Translation\Handlers\PrimaryKeyHandler::class)]
final class PrimaryKeyHandlerTest extends TestCase
{
    private PrimaryKeyHandler $handler;
    private AttributeHelper $attributeHelper;
    public function setUp(): void
    {
        $this->attributeHelper = $this->createMock(AttributeHelper::class);
        $this->handler = new PrimaryKeyHandler($this->attributeHelper);
    }

    public function testSupportsReturnsTrueWhenPropertyIsId(): void
    {
        $prop = new ReflectionProperty(DummyEntity::class, 'id');
        $args = new TranslationArgs(123, 'en', 'de')->setProperty($prop);
        $this->attributeHelper->method('isId')->with($prop)->willReturn(true);
        self::assertTrue($this->handler->supports($args));
    }

    public function testSupportsReturnsFalseWhenPropertyIsNotId(): void
    {
        $prop = new ReflectionProperty(DummyEntity::class, 'name');
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

/**
 * Dummy entity für ReflectionProperty
 */
final class DummyEntity
{
    public int $id = 1;
    public string $name = 'test';
}
