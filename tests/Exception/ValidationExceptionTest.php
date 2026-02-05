<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Exception\ValidationException;

#[CoversClass(ValidationException::class)]
final class ValidationExceptionTest extends TestCase
{
    public function testExtendsLogicException(): void
    {
        $exception = new ValidationException([]);

        self::assertInstanceOf(\LogicException::class, $exception);
    }

    public function testMessageFormatWithSingleError(): void
    {
        $error = new \LogicException('First error message');
        $exception = new ValidationException([$error]);

        self::assertStringContainsString('TMI Translation validation failed with 1 error(s)', $exception->getMessage());
        self::assertStringContainsString('First error message', $exception->getMessage());
    }

    public function testMessageFormatWithMultipleErrors(): void
    {
        $error1 = new \LogicException('Error one');
        $error2 = new \LogicException('Error two');
        $error3 = new \LogicException('Error three');

        $exception = new ValidationException([$error1, $error2, $error3]);

        self::assertStringContainsString('TMI Translation validation failed with 3 error(s)', $exception->getMessage());
        self::assertStringContainsString('Error one', $exception->getMessage());
        self::assertStringContainsString('Error two', $exception->getMessage());
        self::assertStringContainsString('Error three', $exception->getMessage());
    }

    public function testGetErrorsReturnsErrorsArray(): void
    {
        $error1 = new \LogicException('First');
        $error2 = new \LogicException('Second');

        $exception = new ValidationException([$error1, $error2]);

        $errors = $exception->getErrors();

        self::assertCount(2, $errors);
        self::assertSame($error1, $errors[0]);
        self::assertSame($error2, $errors[1]);
    }

    public function testEmptyArrayCase(): void
    {
        $exception = new ValidationException([]);

        self::assertStringContainsString('TMI Translation validation failed with 0 error(s)', $exception->getMessage());
        self::assertSame([], $exception->getErrors());
    }
}
