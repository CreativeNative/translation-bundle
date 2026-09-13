<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Exception\ClassLevelAttributeConflictException;

#[CoversClass(ClassLevelAttributeConflictException::class)]
final class ClassLevelAttributeConflictExceptionTest extends TestCase
{
    public function testNamesTheClassAndTheWayOut(): void
    {
        $exception = ClassLevelAttributeConflictException::forClass('App\Entity\SeoMetadata');

        self::assertStringStartsWith('Class-level attribute conflict on App\Entity\SeoMetadata: ', $exception->getMessage());
        self::assertStringContainsString('a class cannot default to being copied AND cleared', $exception->getMessage());
        self::assertStringContainsString('Solution: keep one class-level attribute', $exception->getMessage());
    }
}
