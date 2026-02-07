<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Event;

use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Event\TranslateEvent;

final class TranslateEventTest extends TestCase
{
    public function testEventProperties(): void
    {
        $source     = new \stdClass();
        $translated = new \stdClass();
        $locale     = 'en_US';

        $event = new TranslateEvent($source, $locale, $translated);

        // Test getters
        self::assertSame($source, $event->getSourceEntity());
        self::assertSame($translated, $event->getTranslatedEntity());
        self::assertSame($locale, $event->getLocale());

        // Test constants
        self::assertSame('tmi_translation.pre_translate', TranslateEvent::PRE_TRANSLATE);
        self::assertSame('tmi_translation.post_translate', TranslateEvent::POST_TRANSLATE);
    }

    public function testTranslatedEntityCanBeNull(): void
    {
        $source = new \stdClass();
        $locale = 'fr';

        $event = new TranslateEvent($source, $locale);

        self::assertSame($source, $event->getSourceEntity());
        self::assertNull($event->getTranslatedEntity());
        self::assertSame($locale, $event->getLocale());
    }
}
