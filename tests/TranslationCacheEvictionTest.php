<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\TranslatableRemover;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * A translation that was removed and flushed must not come back from the translation
 * cache. Negative proof against 5.1 (`c9cf400`): the first test's `assertNotSame` failed
 * -- the dead instance was handed back, `getOrTranslate()` persisted it again, and the
 * new row carried the OLD title instead of a fresh translation of the current source.
 * The flush had nulled its generated id, so the UnitOfWork reported it as STATE_NEW,
 * indistinguishable from a fresh clone; only eviction on postRemove can tell.
 */
final class TranslationCacheEvictionTest extends IntegrationTestCase
{
    public function testARemovedTranslationIsTranslatedAfreshInsteadOfResurrected(): void
    {
        $source = new Scalar()->setLocale('en_US')->setTitle('Old title');
        $this->entityManager()->persist($source);
        $this->entityManager()->flush();

        $stale = $this->translator()->getOrTranslate($source, self::TARGET_LOCALE);
        self::assertInstanceOf(Scalar::class, $stale);
        $this->entityManager()->flush();
        $staleId = $stale->getId();
        self::assertNotNull($staleId);
        self::assertSame($stale, $this->translationCache()->get($source->getTuuid()->getValue(), self::TARGET_LOCALE));

        $this->entityManager()->remove($stale);
        $this->entityManager()->flush();
        self::assertNull($this->translationCache()->get($source->getTuuid()->getValue(), self::TARGET_LOCALE), 'postRemove evicted the entry');

        // Change the source in between, so "old vs fresh" is observable on the result.
        $source->setTitle('New title');
        $this->entityManager()->flush();

        $fresh = $this->translator()->getOrTranslate($source, self::TARGET_LOCALE);
        $this->entityManager()->flush();

        self::assertInstanceOf(Scalar::class, $fresh);
        self::assertNotSame($stale, $fresh, 'a removed instance came back from the cache');
        self::assertNotSame($staleId, $fresh->getId());
        self::assertSame('New title', $fresh->getTitle(), 'a fresh translation of the current source, not the dead row');

        $this->entityManager()->clear();
        self::assertNull($this->entityManager()->find(Scalar::class, $staleId));
        self::assertNotNull($this->entityManager()->find(Scalar::class, $fresh->getId()));
    }

    public function testRemovingAllLocaleVariantsEvictsEveryLocale(): void
    {
        $tuuid  = Tuuid::generate();
        $source = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $this->entityManager()->persist($source);
        $this->entityManager()->flush();

        $de = $this->translator()->getOrTranslate($source, 'de_DE');
        $it = $this->translator()->getOrTranslate($source, 'it_IT');
        $this->entityManager()->flush();

        $cache = $this->translationCache();
        self::assertSame($de, $cache->get($tuuid->getValue(), 'de_DE'));
        self::assertSame($it, $cache->get($tuuid->getValue(), 'it_IT'));

        $remover = new TranslatableRemover($this->entityManager(), new LocaleVariantFinder($this->entityManager()));
        $remover->removeAllLocaleVariants($source);
        $this->entityManager()->flush();

        foreach (['en_US', 'de_DE', 'it_IT'] as $locale) {
            self::assertNull($cache->get($tuuid->getValue(), $locale), $locale.' was evicted');
        }
    }

    public function testRemovingOneVariantKeepsTheSiblingsCached(): void
    {
        $tuuid  = Tuuid::generate();
        $source = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $this->entityManager()->persist($source);
        $this->entityManager()->flush();

        $de = $this->translator()->getOrTranslate($source, 'de_DE');
        $it = $this->translator()->getOrTranslate($source, 'it_IT');
        $this->entityManager()->flush();

        $this->entityManager()->remove($de);
        $this->entityManager()->flush();

        self::assertNull($this->translationCache()->get($tuuid->getValue(), 'de_DE'));
        self::assertSame($it, $this->translationCache()->get($tuuid->getValue(), 'it_IT'));
    }
}
