<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Exception;

/**
 * Exception thrown when incompatible attributes are used on the same property.
 *
 * This typically occurs when both #[SharedAmongstTranslations] and #[EmptyOnTranslate]
 * are applied to the same property, which is a logical contradiction.
 */
final class AttributeConflictException extends \LogicException
{
    public function __construct(
        private readonly string $className,
        private readonly string $propertyName,
        private readonly string $attribute1,
        private readonly string $attribute2,
    ) {
        parent::__construct($this->buildMessage());
    }

    private function buildMessage(): string
    {
        return <<<MSG
Attribute conflict on {$this->className}::\${$this->propertyName}

The property has both #[{$this->attribute1}] and #[{$this->attribute2}] attributes.
These attributes are mutually exclusive:
- #[SharedAmongstTranslations]: Value stays identical across all locales
- #[EmptyOnTranslate]: Value is cleared when creating a new translation

A value cannot both stay the same AND be cleared during translation.

Solution: Remove one of the attributes.

Example of valid usage:

    // Option 1: Keep value across translations (shared content)
    #[SharedAmongstTranslations]
    #[ORM\\Column]
    private string \$videoUrl;

    // Option 2: Clear value for new translation (locale-specific cache)
    #[EmptyOnTranslate]
    #[ORM\\Column(nullable: true)]
    private ?string \$cachedSlug = null;
MSG;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    public function getAttribute1(): string
    {
        return $this->attribute1;
    }

    public function getAttribute2(): string
    {
        return $this->attribute2;
    }
}
