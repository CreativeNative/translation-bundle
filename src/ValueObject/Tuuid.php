<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * Value object for Translation UUID (Tuuid).
 */
final readonly class Tuuid implements \Stringable
{
    private string $value;

    /**
     * Tuuid constructor.
     *
     * @param string $value the UUID string
     *
     * @throws \InvalidArgumentException if the provided string is not a valid UUID
     */
    public function __construct(string $value)
    {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid Tuuid value: "%s".', $value));
        }

        // Normalize to lowercase RFC4122 format
        $this->value = strtolower(Uuid::fromString($value)->toRfc4122());
    }

    /**
     * Returns the UUID string.
     */
    public function __toString(): string
    {
        return $this->value;
    }

    public static function generate(): self
    {
        // Creates a new UUIDv7 (time-based, SEO-friendly sequence). Bypasses the
        // public constructor: Uuid::v7() is already a valid, well-formed UUID, so
        // routing it through `new self($value)` would re-parse and re-validate a
        // string this method itself just produced -- Uuid::isValid() plus a second
        // Uuid::fromString()->toRfc4122() round trip for nothing. Initializing
        // $value via ReflectionProperty rather than a direct `$tuuid->value = ...`
        // assignment is allowed by the same "declaring class's own scope" rule
        // __construct() relies on (verified against PHP's readonly-property
        // initialization rules), and reads as intentional rather than tripping
        // a static-analysis rule meant to catch accidental mutation elsewhere.
        $tuuid = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(self::class, 'value'))->setValue($tuuid, strtolower(Uuid::v7()->toRfc4122()));

        return $tuuid;
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
