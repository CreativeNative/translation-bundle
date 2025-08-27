<?php

namespace TMI\TranslationBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class TranslateEvent extends Event
{
    /**
     * Event called before translation is done.
     */
    public const PRE_TRANSLATE = 'tmi_translation.pre_translate';

    /**
     * Event called after translation is done.
     */
    public const POST_TRANSLATE = 'tmi_translation.post_translate';

    /**
     * The source entity being translated.
     */
    protected object $sourceEntity;

    /**
     * The translated entity.
     */
    protected ?object $translatedEntity;

    /**
     * The target locale
     */
    private string $locale;

    public function __construct(object $sourceEntity, string $locale, object $translatedEntity = null)
    {
        $this->sourceEntity = $sourceEntity;
        $this->locale = $locale;
        $this->translatedEntity = $translatedEntity;
    }

    public function getSourceEntity(): object
    {
        return $this->sourceEntity;
    }

    public function getTranslatedEntity(): ?object
    {
        return $this->translatedEntity;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
