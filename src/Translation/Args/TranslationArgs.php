<?php

declare(strict_types=1);

namespace TMI\TranslationBundle\Translation\Args;

use ReflectionProperty;

/**
 * Translation args DTO.
 */
final class TranslationArgs
{
    /** @var mixed|null */
    private mixed $translatedParent = null;

    private ?ReflectionProperty $property = null;

    public function __construct(
        private mixed $dataToBeTranslated,
        private ?string $sourceLocale = null,
        private ?string $targetLocale = null,
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
    public function getSourceLocale(): ?string
    {
        return $this->sourceLocale;
    }

    /**
     * Sets the locale of the original data.
     */
    public function setSourceLocale(?string $sourceLocale): self
    {
        $this->sourceLocale = $sourceLocale;

        return $this;
    }

    /**
     * Returns the locale of the translated data.
     */
    public function getTargetLocale(): ?string
    {
        return $this->targetLocale;
    }

    /**
     * Sets the locale of the translated data.
     */
    public function setTargetLocale(?string $targetLocale): self
    {
        $this->targetLocale = $targetLocale;

        return $this;
    }

    /**
     * Returns the parent of the data translation.
     * Only set when translating association.
     *
     * @return mixed|null
     */
    public function getTranslatedParent(): mixed
    {
        return $this->translatedParent;
    }

    /**
     * Sets the parent of the data translation.
     *
     * @param mixed $translatedParent
     * @return TranslationArgs
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
    public function getProperty(): ?ReflectionProperty
    {
        return $this->property;
    }

    /**
     * Sets the property associated to the translation.
     */
    public function setProperty(?ReflectionProperty $property): self
    {
        $this->property = $property;

        return $this;
    }
}
