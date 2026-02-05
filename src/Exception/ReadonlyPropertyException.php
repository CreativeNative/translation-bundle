<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Exception;

/**
 * Exception thrown when #[EmptyOnTranslate] is used on a readonly property.
 *
 * Readonly properties cannot be modified after initialization, making
 * #[EmptyOnTranslate] impossible to apply at translation time.
 */
final class ReadonlyPropertyException extends \LogicException
{
    public function __construct(
        private readonly string $className,
        private readonly string $propertyName,
    ) {
        parent::__construct($this->buildMessage());
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    private function buildMessage(): string
    {
        return <<<MSG
Invalid #[EmptyOnTranslate] on readonly property {$this->className}::\${$this->propertyName}

Readonly properties cannot be modified after initialization, but #[EmptyOnTranslate]
requires setting the property to null/empty when creating a translation.

Why this conflicts:
- readonly properties can only be set once (in constructor or property declaration)
- #[EmptyOnTranslate] needs to clear the value during translation

Solution: Remove the readonly modifier OR remove #[EmptyOnTranslate].

Example of valid usage:

    // Option 1: Remove readonly (allow clearing during translation)
    #[EmptyOnTranslate]
    #[ORM\\Column(nullable: true)]
    private ?string \$cachedSlug = null;

    // Option 2: Remove EmptyOnTranslate (keep readonly, value persists)
    #[ORM\\Column]
    private readonly string \$immutableValue;
MSG;
    }
}
