<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\EventSubscriber;

use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\CoversClass;
use Tmi\TranslationBundle\Doctrine\EventSubscriber\TranslatableEventSubscriber;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\IntegrationTestCase;

/**
 * The subscriber reaches Doctrine through the bundle's service definition alone --
 * no test kernel override, no hand-built instance in IntegrationTestCase::setUp().
 * Negative proof against 5.1: the suite registered its own instance and the kernel
 * overrode the service with a dead `doctrine.event_subscriber` tag, so the container
 * path was never exercised; without it, prePersist below would leave the locale null.
 */
#[CoversClass(TranslatableEventSubscriber::class)]
final class TranslatableEventSubscriberRegistrationTest extends IntegrationTestCase
{
    public function testTheBundleRegistersExactlyOneInstanceForItsThreeEvents(): void
    {
        $eventManager = $this->entityManager()->getEventManager();
        $instances    = [];

        foreach ([Events::prePersist, Events::postLoad, Events::onFlush] as $event) {
            $subscribers = array_values(array_filter(
                $eventManager->getListeners($event),
                static fn (object $listener): bool => $listener instanceof TranslatableEventSubscriber,
            ));

            self::assertCount(1, $subscribers, $event.' is served by exactly one subscriber instance');
            $instances[] = $subscribers[0];
        }

        self::assertSame($instances[0], $instances[1]);
        self::assertSame($instances[1], $instances[2], 'one service instance for all three events');
    }

    public function testTheContainerWiredInstanceNormalizesALocaleOnPersist(): void
    {
        $entity = new Scalar()->setTitle('no locale set');

        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();

        self::assertSame('en_US', $entity->getLocale(), 'prePersist fell back to the configured default locale');
        self::assertTrue($entity->hasTuuid(), 'prePersist generated the Tuuid');
    }
}
