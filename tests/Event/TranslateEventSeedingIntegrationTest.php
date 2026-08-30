<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Event;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tmi\TranslationBundle\Event\TranslateEvent;
use Tmi\TranslationBundle\Fixtures\Entity\Seeding\EmptySeeded;
use Tmi\TranslationBundle\Test\IntegrationTestCase;

/**
 * Regression for the slug-cloning workaround: consumers whose variants are
 * seeded empty (copy_source: false) need a supported seam to mint
 * locale-correct placeholders. POST_TRANSLATE is that seam — it fires after
 * the variant is constructed and before it is persisted, so mutations land in
 * the persisted row.
 */
final class TranslateEventSeedingIntegrationTest extends IntegrationTestCase
{
    public function testPostTranslateListenerSeedsPlaceholdersOnEmptyVariants(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $listener = static function (TranslateEvent $event): void {
            $variant = $event->getTranslatedEntity();

            if (!$variant instanceof EmptySeeded || null !== $variant->getSlug()) {
                return;
            }

            $variant->setSlug(sprintf('draft-%s-%s', $event->getLocale(), (string) $variant->getTuuid()));
        };

        $dispatcher->addListener(TranslateEvent::POST_TRANSLATE, $listener);

        try {
            $source = new EmptySeeded()->setLocale('en_US')->setTitle('Sea view villa')->setSlug('sea-view-villa');
            $this->entityManager()->persist($source);
            $this->entityManager()->flush();

            $variant = $this->translator()->translateAndPersist($source, 'de_DE');
            $this->entityManager()->flush();

            self::assertInstanceOf(EmptySeeded::class, $variant);

            // copySource: false seeded the variant empty; the listener minted a
            // locale-correct placeholder instead of cloning the source's slug.
            self::assertNull($variant->getTitle());
            self::assertSame('draft-de_DE-'.$source->getTuuid(), $variant->getSlug());

            $variantId = $variant->getId();
            self::assertNotNull($variantId);

            // The placeholder reached the database row.
            $this->entityManager()->clear();
            $this->entityManager()->getFilters()->disable('tmi_translation_locale_filter');

            $reloaded = $this->entityManager()->find(EmptySeeded::class, $variantId);
            self::assertInstanceOf(EmptySeeded::class, $reloaded);
            self::assertSame('draft-de_DE-'.$source->getTuuid(), $reloaded->getSlug());
            self::assertSame('de_DE', $reloaded->getLocale());
        } finally {
            $dispatcher->removeListener(TranslateEvent::POST_TRANSLATE, $listener);
        }
    }
}
