<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\PrimaryKeyHandler;
use Tmi\TranslationBundle\Utils\AttributeHelper;

#[CoversClass(PrimaryKeyHandler::class)]
final class PrimaryKeyHandlerTest extends UnitTestCase
{
    private PrimaryKeyHandler $handler;

    public function setUp(): void
    {
        parent::setUp();

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
        $args = new TranslationArgs(123, 'en_US', 'de_DE')->setProperty($prop);
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
        $args = new TranslationArgs('foo', 'en_US', 'de_DE')->setProperty($prop);
        $this->attributeHelper->method('isId')->with($prop)->willReturn(false);
        self::assertFalse($this->handler->supports($args));
    }

    public function testTranslateAlwaysReturnsNull(): void
    {
        $args = new TranslationArgs(123, 'en_US', 'de_DE');
        self::assertNull($this->handler->translate($args), 'Primary keys must never be translated');
    }

    public function testHandleSharedAmongstTranslationsAlwaysReturnsNull(): void
    {
        $args = new TranslationArgs(123, 'en_US', 'de_DE');
        self::assertNull($this->handler->handleSharedAmongstTranslations($args));
    }

    public function testHandleEmptyOnTranslateAlwaysReturnsNull(): void
    {
        $args = new TranslationArgs(123, 'en_US', 'de_DE');
        self::assertNull($this->handler->handleEmptyOnTranslate($args));
    }
}
