<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\GuidType;
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class TuuidType extends GuidType
{
    public const string NAME = 'tuuid';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): Tuuid|null
    {
        if (null === $value) {
            return Tuuid::generate();
        }

        if ($value instanceof Tuuid) {
            return $value;
        }

        if (is_string($value) && Uuid::isValid($value)) {
            return new Tuuid($value);
        }

        throw new ConversionException(sprintf('Cannot convert "%s" to Tuuid (PHPValue)', get_debug_type($value)));
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string|null
    {
        if (null === $value) {
            return Tuuid::generate()->getValue();
        }

        if ($value instanceof Tuuid) {
            return $value->getValue();
        }

        if (is_string($value) && Uuid::isValid($value)) {
            return new Tuuid($value)->getValue();
        }

        throw new ConversionException(sprintf('Cannot convert "%s" to Tuuid (DatabaseValue)', get_debug_type($value)));
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        // So that SchemaTool does not cause any problems during mapping
        return ['guid'];
    }
}
