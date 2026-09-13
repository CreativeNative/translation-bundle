<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Exception\ReadonlyPropertyException;

#[CoversClass(ReadonlyPropertyException::class)]
final class ReadonlyPropertyExceptionTest extends TestCase
{
    public function testEmptyOnTranslateOnAReadonlyPropertyNamesThePropertyAndTheWayOut(): void
    {
        $exception = ReadonlyPropertyException::forEmptyOnTranslate('App\Entity\Article', 'cachedSlug');

        self::assertStringStartsWith('Invalid #[EmptyOnTranslate] on readonly property App\Entity\Article::$cachedSlug: ', $exception->getMessage());
        self::assertStringContainsString('Solution: drop the readonly modifier, or remove #[EmptyOnTranslate].', $exception->getMessage());
    }

    public function testAWriteDuringTranslateNamesThePropertyAndTheWayOut(): void
    {
        $exception = ReadonlyPropertyException::forWriteDuringTranslate('App\Entity\Article', 'sku');

        self::assertSame(
            'Property App\Entity\Article::$sku is readonly and cannot be reassigned while translating. '
            .'Solution: mark it #[SharedAmongstTranslations] so every locale keeps the same value, or drop the readonly modifier.',
            $exception->getMessage(),
        );
    }
}
