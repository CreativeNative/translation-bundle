<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Exception\ClassLevelAttributeConflictException;

#[CoversClass(ClassLevelAttributeConflictException::class)]
final class ClassLevelAttributeConflictExceptionTest extends TestCase
{
    public function testExtendsLogicException(): void
    {
        $exception = new ClassLevelAttributeConflictException('App\\Entity\\SeoMetadata');

        $parents = class_parents($exception);
        self::assertNotEmpty($parents);
        self::assertContains(\LogicException::class, $parents);
    }

    public function testGetClassNameReturnsCorrectValue(): void
    {
        $exception = new ClassLevelAttributeConflictException('App\\Entity\\SeoMetadata');

        self::assertSame('App\\Entity\\SeoMetadata', $exception->getClassName());
    }

    public function testMessageContainsClassName(): void
    {
        $exception = new ClassLevelAttributeConflictException('App\\Entity\\SeoMetadata');

        self::assertStringContainsString('App\\Entity\\SeoMetadata', $exception->getMessage());
    }

    public function testMessageContainsMutualExclusivityExplanation(): void
    {
        $exception = new ClassLevelAttributeConflictException('App\\Entity\\SeoMetadata');

        self::assertStringContainsString('mutually exclusive', $exception->getMessage());
        self::assertStringContainsString('#[SharedAmongstTranslations]', $exception->getMessage());
        self::assertStringContainsString('#[EmptyOnTranslate]', $exception->getMessage());
    }

    public function testMessageContainsSolution(): void
    {
        $exception = new ClassLevelAttributeConflictException('App\\Entity\\SeoMetadata');

        self::assertStringContainsString('Solution:', $exception->getMessage());
    }
}
