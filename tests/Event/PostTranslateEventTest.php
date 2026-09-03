<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tmi\TranslationBundle\Event\PostTranslateEvent;

final class PostTranslateEventTest extends TestCase
{
    public function testExposesConstructorArgumentsThroughInheritedGetters(): void
    {
        $source     = new \stdClass();
        $translated = new \stdClass();
        $event      = new PostTranslateEvent($source, 'en_US', $translated);

        self::assertSame($source, $event->getSourceEntity());
        self::assertSame('en_US', $event->getLocale());
        self::assertSame($translated, $event->getTranslatedEntity());
    }

    public function testDispatchesUnderItsOwnClassWithNoExplicitEventName(): void
    {
        $dispatcher = new EventDispatcher();
        $received   = null;

        $dispatcher->addListener(PostTranslateEvent::class, static function (PostTranslateEvent $event) use (&$received): void {
            $received = $event;
        });

        $event = new PostTranslateEvent(new \stdClass(), 'de_DE', new \stdClass());
        $dispatcher->dispatch($event);

        self::assertSame($event, $received);
    }
}
