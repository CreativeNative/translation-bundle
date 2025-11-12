<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\GuidType;
use InvalidArgumentException;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class TuuidType extends GuidType
{
    public const string NAME = 'tuuid';

    public function convertToPHPValue($value, AbstractPlatform $platform): ?Tuuid
    {
        if ($value === null) {
            return null;
        }

        try {
            return new Tuuid($value);
        } catch (InvalidArgumentException $e) {
            throw new ConversionException(
                sprintf('Cannot convert "%s" to Tuuid', $value),
                0,
                $e
            );
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): string|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof Tuuid) {
            throw new InvalidArgumentException('Value must be a Tuuid object.');
        }

        return (string)$value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}