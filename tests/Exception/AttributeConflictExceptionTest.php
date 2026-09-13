<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Exception\AttributeConflictException;

#[CoversClass(AttributeConflictException::class)]
final class AttributeConflictExceptionTest extends TestCase
{
    public function testNamesThePropertyBothAttributesAndTheWayOut(): void
    {
        $exception = AttributeConflictException::forSharedAndEmpty('App\Entity\Article', 'cachedSlug');

        self::assertStringStartsWith('Attribute conflict on App\Entity\Article::$cachedSlug: ', $exception->getMessage());
        self::assertStringContainsString('#[SharedAmongstTranslations] and #[EmptyOnTranslate] are mutually exclusive', $exception->getMessage());
        self::assertStringContainsString('Solution: keep #[SharedAmongstTranslations] for a value every locale shares, or #[EmptyOnTranslate]', $exception->getMessage());
    }
}
