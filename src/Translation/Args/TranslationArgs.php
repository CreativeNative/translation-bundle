<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Args;

/**
 * Polymorphic DTO carrying translation context through the handler chain.
 *
 * Data fields use `mixed` by design: the DTO carries entities (TranslatableInterface),
 * embedded objects, scalar property values, and collections depending on the handler
 * processing the current translation step. Callers narrow via instanceof/type checks.
 */
final class TranslationArgs
{
    /** @var mixed */
    private mixed $translatedParent = null;

    private \ReflectionProperty|null $property = null;

    public function __construct(
        private mixed $dataToBeTranslated,
        private string|null $sourceLocale = null,
        private string|null $targetLocale = null,
    ) {
    }

    /**
     * Returns the source data to translate.
     *
     * Type varies by handler context: TranslatableInterface for entity handlers,
     * embedded objects for embeddable handlers, or scalar values for property handlers.
     * Callers narrow via instanceof checks or assertions.
     */
    public function getDataToBeTranslated(): mixed
    {
        return $this->dataToBeTranslated;
    }

    /**
     * Sets the source data that will be translated.
     */
    public function setDataToBeTranslated(mixed $dataToBeTranslated): self
    {
        $this->dataToBeTranslated = $dataToBeTranslated;

        return $this;
    }

    /**
     * Returns the locale of the original data.
     */
    public function getSourceLocale(): string|null
    {
        return $this->sourceLocale;
    }

    /**
     * Sets the locale of the original data.
     */
    public function setSourceLocale(string|null $sourceLocale): self
    {
        $this->sourceLocale = $sourceLocale;

        return $this;
    }

    /**
     * Returns the locale of the translated data.
     */
    public function getTargetLocale(): string|null
    {
        return $this->targetLocale;
    }

    /**
     * Sets the locale of the translated data.
     */
    public function setTargetLocale(string|null $targetLocale): self
    {
        $this->targetLocale = $targetLocale;

        return $this;
    }

    /**
     * Returns the already-translated parent entity.
     *
     * Null for top-level entity translation. Set to the translated parent object
     * when processing sub-properties or associations, so handlers can attach
     * translated values back to the correct parent.
     */
    public function getTranslatedParent(): mixed
    {
        return $this->translatedParent;
    }

    /**
     * Sets the parent of the data translation.
     */
    public function setTranslatedParent(mixed $translatedParent): self
    {
        $this->translatedParent = $translatedParent;

        return $this;
    }

    /**
     * Returns the property being translated, if any.
     *
     * Null for top-level entity translation. Set to the specific ReflectionProperty
     * when translating individual properties, so handlers can inspect attributes
     * (SharedAmongstTranslations, EmptyOnTranslate, Embedded) on the property.
     */
    public function getProperty(): \ReflectionProperty|null
    {
        return $this->property;
    }

    /**
     * Sets the property associated to the translation.
     */
    public function setProperty(\ReflectionProperty|null $property): self
    {
        $this->property = $property;

        return $this;
    }
}
