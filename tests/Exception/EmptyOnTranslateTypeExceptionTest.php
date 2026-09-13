<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Exception\EmptyOnTranslateTypeException;

#[CoversClass(EmptyOnTranslateTypeException::class)]
final class EmptyOnTranslateTypeExceptionTest extends TestCase
{
    public function testNamesThePropertyTheTypeAndTheWayOut(): void
    {
        $exception = EmptyOnTranslateTypeException::forNonNullableObject('App\Entity\Article', 'publishedAt', 'DateTimeImmutable');

        self::assertSame(
            'App\Entity\Article::$publishedAt carries #[EmptyOnTranslate] but is a non-nullable DateTimeImmutable, which has no empty value to translate to. '
            .'Solution: make the property nullable, remove #[EmptyOnTranslate], or use #[SharedAmongstTranslations].',
            $exception->getMessage(),
        );
    }
}
