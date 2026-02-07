<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\ValueObject;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class TuuidTest extends TestCase
{
    public function testConstructWithValidUuid(): void
    {
        $uuidString = Uuid::v4()->toRfc4122();
        $tuuid      = new Tuuid($uuidString);

        self::assertSame(strtolower($uuidString), $tuuid->getValue());
    }

    public function testConstructWithValidUuidUppercase(): void
    {
        $uuidString = strtoupper(Uuid::v4()->toRfc4122());
        $tuuid      = new Tuuid($uuidString);

        self::assertSame(strtolower($uuidString), $tuuid->getValue());
    }

    public function testConstructWithInvalidUuidThrowsException(): void
    {
        self::expectException(\InvalidArgumentException::class);
        new Tuuid('not-a-uuid');
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $uuidString = Uuid::v4()->toRfc4122();
        $tuuid1     = new Tuuid($uuidString);
        $tuuid2     = new Tuuid($uuidString);

        self::assertTrue($tuuid1->equals($tuuid2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $tuuid1 = new Tuuid(Uuid::v4()->toRfc4122());
        $tuuid2 = new Tuuid(Uuid::v4()->toRfc4122());

        self::assertFalse($tuuid1->equals($tuuid2));
    }

    public function testToStringReturnsValue(): void
    {
        $uuidString = Uuid::v4()->toRfc4122();
        $tuuid      = new Tuuid($uuidString);

        self::assertSame($tuuid->getValue(), (string) $tuuid);
    }
}
