<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Event;

/**
 * Dispatched by {@see \Tmi\TranslationBundle\Translation\EntityTranslator} after a
 * top-level translatable entity has been cloned into a target locale --
 * {@see TranslateEvent::getTranslatedEntity()} is populated by this point. Listen for
 * it with `#[AsEventListener(event: PostTranslateEvent::class)]` or
 * `$dispatcher->addListener(PostTranslateEvent::class, ...)`.
 */
final class PostTranslateEvent extends TranslateEvent
{
}
