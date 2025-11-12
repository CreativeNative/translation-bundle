<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Doctrine\Type\TuuidType;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class TuuidTypeTest extends TestCase
{
    private TuuidType $type;
    private AbstractPlatform $platform;

    public function setUp(): void
    {
        $this->type = new TuuidType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    public function testGetName(): void
    {
        $this->assertSame('tuuid', $this->type->getName());
    }

    /**
     * @throws ConversionException
     */
    public function testConvertToPHPValueReturnsNull(): void
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    /**
     * @throws ConversionException
     */
    public function testConvertToPHPValueConvertsValidTuuid(): void
    {
        $uuid = Tuuid::generate();
        $uuidString = $uuid->getValue();

        $tuuid = $this->type->convertToPHPValue($uuidString, $this->platform);

        $this->assertInstanceOf(Tuuid::class, $tuuid);
        $this->assertSame($uuidString, (string) $tuuid);
    }

    public function testConvertToPHPValueThrowsConversionExceptionOnInvalidValue(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Cannot convert "invalid-uuid" to Tuuid');

        $this->type->convertToPHPValue('invalid-uuid', $this->platform);
    }

    /**
     * @throws ConversionException
     */
    public function testConvertToDatabaseValueReturnsNull(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    /**
     * @throws ConversionException
     */
    public function testConvertToDatabaseValueConvertsTuuidToString(): void
    {
        $tuuid = Tuuid::generate();
        $dbValue = $this->type->convertToDatabaseValue($tuuid, $this->platform);

        $this->assertSame((string)$tuuid, $dbValue);
    }

    /**
     * @throws ConversionException
     */
    public function testConvertToDatabaseValueThrowsExceptionOnInvalidObject(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be a Tuuid object.');

        $this->type->convertToDatabaseValue('not-a-tuuid', $this->platform);
    }
}
