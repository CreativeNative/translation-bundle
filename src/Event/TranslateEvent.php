<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base type shared by {@see PreTranslateEvent} and {@see PostTranslateEvent}.
 *
 * EntityTranslator dispatches those two subclasses, never this class
 * directly, and dispatches them by class rather than by a string event name
 * -- so a listener subscribes with `PreTranslateEvent::class` /
 * `PostTranslateEvent::class` (or `#[AsEventListener(event: ...)]`), not a
 * string constant.
 */
class TranslateEvent extends Event
{
    public function __construct(
        /**
         * The source entity being translated.
         */
        protected object $sourceEntity,
        /**
         * The target locale.
         */
        private readonly string $locale,
        /**
         * The translated entity.
         */
        protected object|null $translatedEntity = null,
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
