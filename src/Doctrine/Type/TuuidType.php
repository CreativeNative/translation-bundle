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

    /**
     * A database NULL converts to PHP null, never an exception and never an
     * invented Tuuid. This is DBAL convention, not a relaxation: Doctrine's
     * AbstractHydrator::gatherRowData() calls convertToPHPValue() for every
     * selected column of every hydrated row, including the columns of a
     * translatable relation fetched through a LEFT JOIN that found no match
     * -- where they are all NULL by construction. Throwing there would crash
     * any such fetch-join query outright. A literal NULL that reaches this
     * column on a genuinely persisted row is now also impossible in the
     * normal write path (the "tuuid" column is NOT NULL, see
     * TranslatableTrait, and TranslatableEventSubscriber::prePersist()
     * always assigns one first) -- it can only happen through a write that
     * bypasses the entity layer (a raw insert, a pre-v4 migration), and
     * TranslationDoctorCommand's "null-tuuid" check exists to catch exactly
     * that.
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): Tuuid|null
    {
        if (null === $value) {
            return null;
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
            return null;
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

    /**
     * @return list<string>
     */
    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        // So that SchemaTool does not cause any problems during mapping
        return ['guid'];
    }
}
