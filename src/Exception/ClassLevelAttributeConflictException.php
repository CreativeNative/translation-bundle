<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Exception;

/**
 * Exception thrown when both #[SharedAmongstTranslations] and #[EmptyOnTranslate]
 * are placed at class level on the same embeddable class.
 *
 * Class-level attributes act as defaults for all properties, so having both
 * on the same class is a logical contradiction.
 */
final class ClassLevelAttributeConflictException extends \LogicException
{
    public function __construct(
        private readonly string $className,
    ) {
        parent::__construct($this->buildMessage());
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    private function buildMessage(): string
    {
        return <<<MSG
Class-level attribute conflict on {$this->className}

Both #[SharedAmongstTranslations] and #[EmptyOnTranslate] are placed on class {$this->className}.
These attributes are mutually exclusive at class level:
- #[SharedAmongstTranslations]: All properties default to being shared across locales
- #[EmptyOnTranslate]: All properties default to being cleared on translation

A class cannot default to both behaviors simultaneously.

Solution: Remove one of the class-level attributes. Use property-level attributes for mixed behavior.

Example of valid usage:

    // One class-level default with property-level overrides
    #[SharedAmongstTranslations]
    #[ORM\\Embeddable]
    class SeoMetadata
    {
        // Inherits class-level #[SharedAmongstTranslations]
        #[ORM\\Column]
        private string \$canonicalUrl;

        // Override: this property should be cleared per locale
        #[EmptyOnTranslate]
        #[ORM\\Column(nullable: true)]
        private ?string \$metaDescription = null;
    }
MSG;
    }
}
