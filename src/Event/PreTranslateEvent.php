<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Event;

/**
 * Dispatched by {@see \Tmi\TranslationBundle\Translation\EntityTranslator} before a
 * top-level translatable entity is cloned into a target locale. Listen for it with
 * `#[AsEventListener(event: PreTranslateEvent::class)]` or
 * `$dispatcher->addListener(PreTranslateEvent::class, ...)`.
 */
final class PreTranslateEvent extends TranslateEvent
{
}
