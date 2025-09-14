<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\ScalarHandler;

#[CoversClass(ScalarHandler::class)]
final class ScalarHandlerTest extends UnitTestCase
{
    public function testSupportsScalars(): void
    {
        $handler = new ScalarHandler();

        $args = new TranslationArgs('hello', 'en', 'de');
        self::assertTrue($handler->supports($args));

        $args = new TranslationArgs(new DateTime(), 'en', 'de');
        self::assertTrue($handler->supports($args));

        $args = new TranslationArgs(new stdClass(), 'en', 'de');
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
