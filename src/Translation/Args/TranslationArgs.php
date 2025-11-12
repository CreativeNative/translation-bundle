<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Args;

/**
 * Translation args DTO.
 */
final class TranslationArgs
{
    /** @var mixed|null */
    private mixed $translatedParent = null;

    private \ReflectionProperty|null $property = null;

    public function __construct(
        private mixed $dataToBeTranslated,
        private string|null $sourceLocale = null,
        private string|null $targetLocale = null,
    ) {
    }

    /**
     * Returns the source data that will be translated.
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
     * Returns the parent of the data translation.
     * Only set when translating association.
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
     * Returns the property associated to the translation.
     * Only set when translating association.
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
