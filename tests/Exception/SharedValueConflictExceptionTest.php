<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Exception\SharedValueConflictException;
use Tmi\TranslationBundle\Fixtures\Enum\Priority;

#[CoversClass(SharedValueConflictException::class)]
final class SharedValueConflictExceptionTest extends TestCase
{
    public function testExtendsRuntimeException(): void
    {
        $exception = SharedValueConflictException::forProperty('App\Entity\Listing', 'tuuid-1', 'price', 'de_DE', 1, 'it_IT', 2);

        $parents = class_parents($exception);
        self::assertNotEmpty($parents);
        self::assertContains(\RuntimeException::class, $parents);
    }

    public function testNamesClassPathTuuidAndBothLocales(): void
    {
        $message = SharedValueConflictException::forProperty(
            'App\Entity\Listing',
            'tuuid-1',
            'address.street',
            'de_DE',
            'Hauptstraße 1',
            'it_IT',
            'Via Roma 1',
        )->getMessage();

        self::assertStringContainsString('App\Entity\Listing::$address.street', $message);
        self::assertStringContainsString('tuuid tuuid-1', $message);
        self::assertStringContainsString('"Hauptstraße 1" on locale "de_DE"', $message);
        self::assertStringContainsString('"Via Roma 1" on locale "it_IT"', $message);
        self::assertStringContainsString('propagate_shared_on_flush', $message);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function describedValues(): iterable
    {
        yield 'null' => [null, 'null'];
        yield 'true' => [true, 'true'];
        yield 'false' => [false, 'false'];
        yield 'int' => [42, '"42"'];
        yield 'float' => [1.5, '"1.5"'];
        yield 'string' => ['villa', '"villa"'];
        yield 'enum' => [Priority::High, Priority::class.'::High'];
        yield 'date' => [new \DateTimeImmutable('2026-09-05T10:00:00+02:00'), '2026-09-05T10:00:00+02:00'];
        yield 'stringable' => [
            new class implements \Stringable {
                public function __toString(): string
                {
                    return 'tuuid-like';
                }
            },
            '"tuuid-like"',
        ];
        yield 'object' => [new \stdClass(), \stdClass::class];
        yield 'array' => [[1, 2], 'array'];
    }

    #[DataProvider('describedValues')]
    public function testDescribesEveryValueShapeSafely(mixed $value, string $expected): void
    {
        $message = SharedValueConflictException::forProperty('App\Entity\Listing', 'tuuid-1', 'kind', 'de_DE', $value, 'en_US', 'other')->getMessage();

        self::assertStringContainsString($expected.' on locale "de_DE"', $message);
    }
}
