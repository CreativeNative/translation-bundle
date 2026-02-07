<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\ScalarHandler;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(ScalarHandler::class)]
final class ScalarHandlerTest extends UnitTestCase
{
    public function testSupportsScalars(): void
    {
        $handler = new ScalarHandler();

        $args = new TranslationArgs('hello', 'en_US', 'de_DE');
        self::assertTrue($handler->supports($args));

        $args = new TranslationArgs(new \DateTime(), 'en_US', 'de_DE');
        self::assertTrue($handler->supports($args));

        $args = new TranslationArgs(new \stdClass(), 'en_US', 'de_DE');
        self::assertFalse($handler->supports($args));
    }

    public function testTranslateReturnsSameValue(): void
    {
        $handler = new ScalarHandler();

        $args   = new TranslationArgs('test-value', 'en_US', 'de_DE');
        $result = $handler->translate($args);

        self::assertSame('test-value', $result);
    }

    public function testHandleSharedAmongstTranslationsReturnsSameValue(): void
    {
        $handler = new ScalarHandler();
        $args    = new TranslationArgs('shared-value', 'en_US', 'de_DE');
        $result  = $handler->handleSharedAmongstTranslations($args);

        self::assertSame('shared-value', $result);
    }

    public function testHandleEmptyOnTranslateReturnsNull(): void
    {
        $handler = new ScalarHandler();
        $args    = new TranslationArgs('some-value', 'en_US', 'de_DE');
        $result  = $handler->handleEmptyOnTranslate($args);

        self::assertThat($result, self::isNull());
    }
}
