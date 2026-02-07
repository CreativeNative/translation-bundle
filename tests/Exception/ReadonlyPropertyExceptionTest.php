<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Exception\ReadonlyPropertyException;

#[CoversClass(ReadonlyPropertyException::class)]
final class ReadonlyPropertyExceptionTest extends TestCase
{
    public function testExtendsLogicException(): void
    {
        $exception = new ReadonlyPropertyException(
            'App\\Entity\\Article',
            'createdAt',
        );

        $parents = class_parents($exception);
        self::assertNotEmpty($parents);
        self::assertContains(\LogicException::class, $parents);
    }

    public function testMessageContainsClassName(): void
    {
        $exception = new ReadonlyPropertyException(
            'App\\Entity\\Article',
            'createdAt',
        );

        self::assertStringContainsString('App\\Entity\\Article', $exception->getMessage());
    }

    public function testMessageContainsPropertyName(): void
    {
        $exception = new ReadonlyPropertyException(
            'App\\Entity\\Article',
            'immutableValue',
        );

        self::assertStringContainsString('$immutableValue', $exception->getMessage());
    }

    public function testMessageContainsExplanationOfConflict(): void
    {
        $exception = new ReadonlyPropertyException(
            'App\\Entity\\Article',
            'createdAt',
        );

        $message = $exception->getMessage();

        // Check for explanation of why readonly and EmptyOnTranslate conflict
        self::assertStringContainsString('Readonly properties cannot be modified', $message);
        self::assertStringContainsString('#[EmptyOnTranslate]', $message);
        self::assertStringContainsString('readonly', $message);
    }

    public function testMessageContainsWhyThisConflictsSection(): void
    {
        $exception = new ReadonlyPropertyException(
            'App\\Entity\\Article',
            'createdAt',
        );

        $message = $exception->getMessage();

        self::assertStringContainsString('Why this conflicts:', $message);
        self::assertStringContainsString('readonly properties can only be set once', $message);
    }

    public function testMessageContainsSolutionSuggestion(): void
    {
        $exception = new ReadonlyPropertyException(
            'App\\Entity\\Article',
            'createdAt',
        );

        self::assertStringContainsString('Solution:', $exception->getMessage());
        self::assertStringContainsString('Remove the readonly modifier', $exception->getMessage());
        self::assertStringContainsString('remove #[EmptyOnTranslate]', $exception->getMessage());
    }

    public function testMessageContainsCodeExamples(): void
    {
        $exception = new ReadonlyPropertyException(
            'App\\Entity\\Article',
            'createdAt',
        );

        // Check for code examples in message
        self::assertStringContainsString('Example of valid usage:', $exception->getMessage());
        self::assertStringContainsString('Option 1:', $exception->getMessage());
        self::assertStringContainsString('Option 2:', $exception->getMessage());
    }

    public function testGetClassNameReturnsCorrectValue(): void
    {
        $exception = new ReadonlyPropertyException(
            'App\\Entity\\Article',
            'createdAt',
        );

        self::assertSame('App\\Entity\\Article', $exception->getClassName());
    }

    public function testGetPropertyNameReturnsCorrectValue(): void
    {
        $exception = new ReadonlyPropertyException(
            'App\\Entity\\Article',
            'immutableField',
        );

        self::assertSame('immutableField', $exception->getPropertyName());
    }
}
