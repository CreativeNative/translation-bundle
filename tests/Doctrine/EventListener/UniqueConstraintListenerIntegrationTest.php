<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\EventListener;

use Doctrine\ORM\Events;
use Tmi\TranslationBundle\Doctrine\EventListener\TranslatableIndexListener;
use Tmi\TranslationBundle\Doctrine\EventListener\UniqueConstraintListener;
use Tmi\TranslationBundle\Test\IntegrationTestCase;

/**
 * The "every path" half of the gate: the bundle's own service definition registers the
 * listener with Doctrine's event manager, so the kernel boot in IntegrationTestCase::setUp()
 * (which loads every fixture's metadata) has already run it over the whole fixture set.
 */
final class UniqueConstraintListenerIntegrationTest extends IntegrationTestCase
{
    public function testTheBundleRegistersTheListenerAfterTheIndexListener(): void
    {
        $listeners = $this->entityManager()->getEventManager()->getListeners(Events::loadClassMetadata);

        $classes = array_values(array_map(static fn (object $listener): string => $listener::class, $listeners));

        self::assertContains(UniqueConstraintListener::class, $classes);
        self::assertContains(TranslatableIndexListener::class, $classes);
        self::assertGreaterThan(
            array_search(TranslatableIndexListener::class, $classes, true),
            array_search(UniqueConstraintListener::class, $classes, true),
            'priority -10 puts the unique-constraint check after the index injection',
        );
    }
}
