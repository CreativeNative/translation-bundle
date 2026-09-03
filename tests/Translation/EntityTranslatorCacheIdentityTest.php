<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation;

use Doctrine\ORM\UnitOfWork;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * #13: a cache hit surviving an EntityManager::clear() (import batches, long-running
 * workers) used to be handed straight back to the caller. Doctrine's persist() assumes
 * STATE_NEW for anything the UnitOfWork does not track -- so getOrTranslate() calling
 * persist() on that stale, identifier-carrying instance re-inserted it as a brand-new
 * row instead of reusing the existing one. Silent duplicate, no exception.
 *
 * EntityTranslator now checks the cached instance's real UnitOfWork state (without
 * $assume) at both cache-hit sites: a hit reporting STATE_DETACHED is treated as a
 * miss, falls through to the regular lookup, and gets overwritten in the cache with a
 * freshly resolved, managed instance.
 */
final class EntityTranslatorCacheIdentityTest extends IntegrationTestCase
{
    public function testGetOrTranslateReusesExistingRowAcrossEntityManagerClear(): void
    {
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $this->entityManager()->persist($en);
        $this->entityManager()->flush();

        // First translation: no de_DE variant exists yet, so this creates and
        // persists one -- priming the InMemoryTranslationCache with a managed
        // instance under (tuuid, de_DE).
        $de = $this->translator()->getOrTranslate($en, 'de_DE');
        $this->entityManager()->flush();
        self::assertInstanceOf(Scalar::class, $de);
        $deId = $de->getId();
        self::assertNotNull($deId);

        // Simulate an import batch / worker boundary: clear() detaches every
        // managed instance, including the de_DE clone still sitting in the cache
        // (a long-lived service here, matching a Messenger consumer in production).
        $this->entityManager()->clear();

        $enReloaded = $this->entityManager()->find(Scalar::class, $en->getId());
        self::assertInstanceOf(Scalar::class, $enReloaded);

        $deAgain = $this->translator()->getOrTranslate($enReloaded, 'de_DE');
        $this->entityManager()->flush();

        self::assertInstanceOf(Scalar::class, $deAgain);
        self::assertSame($deId, $deAgain->getId(), 'must reuse the existing de_DE row instead of minting a new one');
        self::assertSame(
            UnitOfWork::STATE_MANAGED,
            $this->entityManager()->getUnitOfWork()->getEntityState($deAgain),
            'the resolved instance must be managed -- not the stale detached one handed back verbatim',
        );

        // A raw row count (filter suspended) rather than findAllLocaleVariants():
        // that method groups by locale, so a duplicate (tuuid, locale) row would
        // silently overwrite the existing entry instead of showing up as a count.
        $finder = new LocaleVariantFinder($this->entityManager());
        $count  = $finder->withoutLocaleFilter(fn (): int => (int) $this->entityManager()
            ->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Scalar::class, 't')
            ->where('t.tuuid = :tuuid')
            ->andWhere('t.locale = :locale')
            ->setParameter('tuuid', (string) $tuuid)
            ->setParameter('locale', 'de_DE')
            ->getQuery()
            ->getSingleScalarResult());

        self::assertSame(1, $count, 'exactly one de_DE row must exist for this tuuid');
    }
}
