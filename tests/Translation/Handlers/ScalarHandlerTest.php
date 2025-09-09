<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use DateTime;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\ScalarHandler;

#[\PHPUnit\Framework\Attributes\CoversClass(\Tmi\TranslationBundle\Translation\Handlers\ScalarHandler::class)]
final class ScalarHandlerTest extends TestCase
{
    public function testSupportsScalars(): void
    {
        $handler = new ScalarHandler();
        $args = new TranslationArgs('hello', 'en', 'de');
        self::assertTrue($handler->supports($args));
        $args = new TranslationArgs(new DateTime(), 'en', 'de');
        self::assertTrue($handler->supports($args));
        $args = new TranslationArgs(new \stdClass(), 'en', 'de');
        self::assertFalse($handler->supports($args));
    }

    public function testTranslateReturnsSameValue(): void
    {
        $handler = new ScalarHandler();
        $args = new TranslationArgs('test-value', 'en', 'de');
        $result = $handler->translate($args);
        self::assertSame('test-value', $result);
    }
}
