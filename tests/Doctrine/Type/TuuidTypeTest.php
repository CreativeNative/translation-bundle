<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\Doctrine\Type\TuuidType;
use Tmi\TranslationBundle\ValueObject\Tuuid;

#[AllowMockObjectsWithoutExpectations]
final class TuuidTypeTest extends TestCase
{
    private TuuidType $type;
    private AbstractPlatform $platform;

    public function setUp(): void
    {
        parent::setUp();
        $this->type     = new TuuidType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    public function testGetName(): void
    {
        self::assertSame('tuuid', $this->type->getName());
    }

    /**
     * Convert valid Tuuid string to PHP value.
     *
     * @throws ConversionException
     */
    public function testConvertToPHPValueFromString(): void
    {
        $uuid     = Tuuid::generate();
        $phpValue = $this->type->convertToPHPValue($uuid->getValue(), $this->platform);

        self::assertSame($uuid->getValue(), $phpValue->getValue());
        self::assertSame($uuid->getValue(), (string) $phpValue);
    }

    /**
     * Tuuid instance returns itself.
     *
     * @throws ConversionException
     */
    public function testConvertToPHPValueFromTuuid(): void
    {
        $uuid     = Tuuid::generate();
        $phpValue = $this->type->convertToPHPValue($uuid, $this->platform);

        self::assertSame($uuid, $phpValue);
    }

    /**
     * Null input generates a new Tuuid.
     *
     * @throws ConversionException
     */
    public function testConvertToPHPValueFromNull(): void
    {
        $phpValue = $this->type->convertToPHPValue(null, $this->platform);

        self::assertNotEmpty($phpValue->getValue());
        self::assertTrue(Uuid::isValid((string) $phpValue));
    }

    /**
     * Invalid string throws ConversionException.
     */
    public function testConvertToPHPValueThrowsExceptionOnInvalid(): void
    {
        self::expectException(ConversionException::class);
        self::expectExceptionMessage('Cannot convert "string" to Tuuid (PHPValue)');

        $this->type->convertToPHPValue('invalid-uuid', $this->platform);
    }

    /**
     * Convert Tuuid to database string.
     *
     * @throws ConversionException
     */
    public function testConvertToDatabaseValueFromTuuid(): void
    {
        $uuid    = Tuuid::generate();
        $dbValue = $this->type->convertToDatabaseValue($uuid, $this->platform);

        self::assertSame($uuid->getValue(), $dbValue);
    }

    /**
     * Convert valid string to database value.
     *
     * @throws ConversionException
     */
    public function testConvertToDatabaseValueFromString(): void
    {
        $uuid    = Tuuid::generate();
        $dbValue = $this->type->convertToDatabaseValue($uuid->getValue(), $this->platform);

        self::assertSame($uuid->getValue(), $dbValue);
    }

    /**
     * Null input generates a new database value.
     *
     * @throws ConversionException
     */
    public function testConvertToDatabaseValueFromNull(): void
    {
        $dbValue = $this->type->convertToDatabaseValue(null, $this->platform);

        self::assertNotEmpty($dbValue);
        self::assertTrue(Uuid::isValid($dbValue));
    }

    /**
     * Invalid input throws ConversionException when converting to database value.
     */
    public function testConvertToDatabaseValueThrowsExceptionOnInvalid(): void
    {
        self::expectException(ConversionException::class);
        self::expectExceptionMessage('Cannot convert "string" to Tuuid (DatabaseValue)');

        $this->type->convertToDatabaseValue('not-a-tuuid', $this->platform);
    }

    /**
     * Strict type enforcement: objects that are not Tuuid should throw.
     */
    public function testConvertToDatabaseValueRejectsInvalidObjects(): void
    {
        self::expectException(ConversionException::class);
        self::expectExceptionMessage('Cannot convert "stdClass" to Tuuid (DatabaseValue)');

        $this->type->convertToDatabaseValue(new \stdClass(), $this->platform);
    }

    /**
     * Strict type enforcement: integers should throw as well.
     */
    public function testConvertToDatabaseValueRejectsIntegers(): void
    {
        self::expectException(ConversionException::class);
        self::expectExceptionMessage('Cannot convert "int" to Tuuid (DatabaseValue)');

        $this->type->convertToDatabaseValue(12345, $this->platform);
    }

    /**
     * Strict type enforcement for convertToPHPValue: integer should throw.
     */
    public function testConvertToPHPValueRejectsIntegers(): void
    {
        self::expectException(ConversionException::class);
        self::expectExceptionMessage('Cannot convert "int" to Tuuid (PHPValue)');

        $this->type->convertToPHPValue(12345, $this->platform);
    }

    /**
     * Strict type enforcement for convertToPHPValue: object should throw.
     */
    public function testConvertToPHPValueRejectsObjects(): void
    {
        self::expectException(ConversionException::class);
        self::expectExceptionMessage('Cannot convert "stdClass" to Tuuid (PHPValue)');

        $this->type->convertToPHPValue(new \stdClass(), $this->platform);
    }
}
