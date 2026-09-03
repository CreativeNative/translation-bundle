<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Context;

/**
 * "Translate this property value" -- the call shape used by
 * DoctrineObjectHandler::translateProperties() for every non-entity property (scalar,
 * embeddable, Collection) and by the OneToMany/ManyToMany handlers for each collection
 * element, whatever that value's own type turns out to be.
 */
final class PropertyTranslationContext extends TranslationContext
{
    public function __construct(
        private mixed $value,
        string|null $sourceLocale = null,
        string|null $targetLocale = null,
    ) {
        parent::__construct($sourceLocale, $targetLocale);
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getSubject(): mixed
    {
        return $this->value;
    }

    public function setSubject(mixed $subject): static
    {
        $this->value = $subject;

        return $this;
    }
}
