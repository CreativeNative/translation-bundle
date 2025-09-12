<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class TranslateEvent extends Event
{
    /**
     * Event called before translation is done.
     */
    public const string PRE_TRANSLATE = 'tmi_translation.pre_translate';

    /**
     * Event called after translation is done.
     */
    public const string POST_TRANSLATE = 'tmi_translation.post_translate';

    public function __construct(
        /**
         * The source entity being translated.
         */
        protected object $sourceEntity,
        /**
         * The target locale
         */
        private readonly string $locale,
        /**
         * The translated entity.
         */
        protected object|null $translatedEntity = null
    ) {
    }

    public function getSourceEntity(): object
    {
        return $this->sourceEntity;
    }

    public function getTranslatedEntity(): object|null
    {
        return $this->translatedEntity;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
