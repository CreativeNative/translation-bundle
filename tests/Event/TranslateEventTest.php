<?php

namespace TMI\TranslationBundle\Test\Event;

use PHPUnit\Framework\TestCase;
use stdClass;
use TMI\TranslationBundle\Event\TranslateEvent;

final class TranslateEventTest extends TestCase
{
    public function testEventProperties(): void
    {
        $source = new stdClass();
        $translated = new stdClass();
        $locale = 'en';

        $event = new TranslateEvent($source, $locale, $translated);

        // Test getters
        $this->assertSame($source, $event->getSourceEntity());
        $this->assertSame($translated, $event->getTranslatedEntity());
        $this->assertSame($locale, $event->getLocale());

        // Test constants
        $this->assertSame('tmi_translation.pre_translate', TranslateEvent::PRE_TRANSLATE);
        $this->assertSame('tmi_translation.post_translate', TranslateEvent::POST_TRANSLATE);
    }

    public function testTranslatedEntityCanBeNull(): void
    {
        $source = new stdClass();
        $locale = 'fr';

        $event = new TranslateEvent($source, $locale);

        $this->assertSame($source, $event->getSourceEntity());
        $this->assertNull($event->getTranslatedEntity());
        $this->assertSame($locale, $event->getLocale());
    }
}
