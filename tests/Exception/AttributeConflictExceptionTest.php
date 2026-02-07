<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Exception\AttributeConflictException;

#[CoversClass(AttributeConflictException::class)]
final class AttributeConflictExceptionTest extends TestCase
{
    public function testExtendsLogicException(): void
    {
        $exception = new AttributeConflictException(
            'App\\Entity\\Article',
            'title',
            'SharedAmongstTranslations',
            'EmptyOnTranslate',
        );

        $parents = class_parents($exception);
        self::assertNotEmpty($parents);
        self::assertContains(\LogicException::class, $parents);
    }

    public function testMessageContainsClassName(): void
    {
        $exception = new AttributeConflictException(
            'App\\Entity\\Article',
            'title',
            'SharedAmongstTranslations',
            'EmptyOnTranslate',
        );

        self::assertStringContainsString('App\\Entity\\Article', $exception->getMessage());
    }

    public function testMessageContainsPropertyName(): void
    {
        $exception = new AttributeConflictException(
            'App\\Entity\\Article',
            'cachedSlug',
            'SharedAmongstTranslations',
            'EmptyOnTranslate',
        );

        self::assertStringContainsString('$cachedSlug', $exception->getMessage());
    }

    public function testMessageContainsBothAttributeNames(): void
    {
        $exception = new AttributeConflictException(
            'App\\Entity\\Article',
            'title',
            'SharedAmongstTranslations',
            'EmptyOnTranslate',
        );

        self::assertStringContainsString('#[SharedAmongstTranslations]', $exception->getMessage());
        self::assertStringContainsString('#[EmptyOnTranslate]', $exception->getMessage());
    }

    public function testMessageContainsSolutionSuggestion(): void
    {
        $exception = new AttributeConflictException(
            'App\\Entity\\Article',
            'title',
            'SharedAmongstTranslations',
            'EmptyOnTranslate',
        );

        self::assertStringContainsString('Solution:', $exception->getMessage());
        self::assertStringContainsString('Remove one of the attributes', $exception->getMessage());
    }

    public function testMessageContainsCodeExamples(): void
    {
        $exception = new AttributeConflictException(
            'App\\Entity\\Article',
            'title',
            'SharedAmongstTranslations',
            'EmptyOnTranslate',
        );

        // Check for code examples in message
        self::assertStringContainsString('Example of valid usage:', $exception->getMessage());
        self::assertStringContainsString('Option 1:', $exception->getMessage());
        self::assertStringContainsString('Option 2:', $exception->getMessage());
    }

    public function testGetClassNameReturnsCorrectValue(): void
    {
        $exception = new AttributeConflictException(
            'App\\Entity\\Article',
            'title',
            'SharedAmongstTranslations',
            'EmptyOnTranslate',
        );

        self::assertSame('App\\Entity\\Article', $exception->getClassName());
    }

    public function testGetPropertyNameReturnsCorrectValue(): void
    {
        $exception = new AttributeConflictException(
            'App\\Entity\\Article',
            'cachedSlug',
            'SharedAmongstTranslations',
            'EmptyOnTranslate',
        );

        self::assertSame('cachedSlug', $exception->getPropertyName());
    }

    public function testGetAttribute1ReturnsCorrectValue(): void
    {
        $exception = new AttributeConflictException(
            'App\\Entity\\Article',
            'title',
            'SharedAmongstTranslations',
            'EmptyOnTranslate',
        );

        self::assertSame('SharedAmongstTranslations', $exception->getAttribute1());
    }

    public function testGetAttribute2ReturnsCorrectValue(): void
    {
        $exception = new AttributeConflictException(
            'App\\Entity\\Article',
            'title',
            'SharedAmongstTranslations',
            'EmptyOnTranslate',
        );

        self::assertSame('EmptyOnTranslate', $exception->getAttribute2());
    }
}
