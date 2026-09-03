<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Handlers\ScalarHandler;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(ScalarHandler::class)]
final class ScalarHandlerTest extends UnitTestCase
{
    public function testSupportsScalars(): void
    {
        $handler = new ScalarHandler();

        self::assertTrue($handler->supports($this->propertyContext('hello')));
        self::assertTrue($handler->supports($this->propertyContext(new \DateTime())));
        self::assertFalse($handler->supports($this->propertyContext(new \stdClass())));
    }

    public function testTranslateReturnsSameValue(): void
    {
        $handler = new ScalarHandler();

        $result = $handler->translate($this->propertyContext('test-value'));

        self::assertSame('test-value', $result);
    }

    public function testTranslateReturnsSameValueWhenShared(): void
    {
        $handler = new ScalarHandler();

        $context = $this->propertyContext('shared-value')->setShared(true);
        $result  = $handler->translate($context);

        self::assertSame('shared-value', $result);
    }

    public function testTranslateReturnsNullWhenEmpty(): void
    {
        $handler = new ScalarHandler();

        $context = $this->propertyContext('some-value')->setEmpty(true);
        $result  = $handler->translate($context);

        self::assertThat($result, self::isNull());
    }
}
