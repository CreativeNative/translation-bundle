<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tmi\TranslationBundle\Event\PreTranslateEvent;

final class PreTranslateEventTest extends TestCase
{
    public function testExposesConstructorArgumentsThroughInheritedGetters(): void
    {
        $source = new \stdClass();
        $event  = new PreTranslateEvent($source, 'en_US');

        self::assertSame($source, $event->getSourceEntity());
        self::assertSame('en_US', $event->getLocale());
        self::assertNull($event->getTranslatedEntity());
    }

    public function testDispatchesUnderItsOwnClassWithNoExplicitEventName(): void
    {
        $dispatcher = new EventDispatcher();
        $received   = null;

        $dispatcher->addListener(PreTranslateEvent::class, static function (PreTranslateEvent $event) use (&$received): void {
            $received = $event;
        });

        $event = new PreTranslateEvent(new \stdClass(), 'de_DE');
        $dispatcher->dispatch($event);

        self::assertSame($event, $received);
    }
}
