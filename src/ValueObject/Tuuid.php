<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\ValueObject;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Value object for Translation UUID (Tuuid).
 */
final class Tuuid
{
    private string $value;

    /**
     * Tuuid constructor.
     *
     * @param string $value The UUID string.
     *
     * @throws InvalidArgumentException if the provided string is not a valid UUID.
     */
    public function __construct(string $value)
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidArgumentException(sprintf('Invalid Tuuid value: "%s".', $value));
        }

        // Normalize to lowercase RFC4122 format
        $this->value = strtolower(Uuid::fromString($value)->toRfc4122());
    }

    public static function generate(): self
    {
        // Creates a new UUIDv7 (time-based, SEO-friendly sequence)
        return new self(Uuid::v7()->toRfc4122());
    }

    /**
     * Returns the UUID string.
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Returns the raw value for comparisons.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Compares this Tuuid with another one.
     */
    public function equals(Tuuid $other): bool
    {
        return $this->value === $other->getValue();
    }
}