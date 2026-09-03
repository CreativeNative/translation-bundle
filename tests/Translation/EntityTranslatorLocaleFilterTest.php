<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation;

use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalParent;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * #12: warmupTranslations() and TranslatableEntityHandler::translate() used to look
 * for an existing locale variant through the entity's own repository / query builder
 * -- a query that runs under whatever locale the LocaleFilter is currently enabled
 * for. With the filter active and pinned to the source locale, that lookup could
 * never see an already-persisted variant in the target locale and minted a
 * duplicate row on every translate() call. LocaleVariantFinder fixes this by
 * suspending the filter for the duration of the lookup.
 */
final class EntityTranslatorLocaleFilterTest extends IntegrationTestCase
{
    public function testTranslateReturnsExistingVariantWhileLocaleFilterIsActive(): void
    {
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $this->entityManager()->persist($en);
        $this->entityManager()->persist($de);
        $this->entityManager()->flush();

        $deId = $de->getId();

        $filters = $this->entityManager()->getFilters();
        $filter  = $filters->enable(LocaleFilter::NAME);
        self::assertInstanceOf(LocaleFilter::class, $filter);
        $filter->setLocale('en_US');

        try {
            $result = $this->translator()->translate($en, 'de_DE');
            self::assertInstanceOf(Scalar::class, $result);

            self::assertSame($deId, $result->getId(), 'translate() must reuse the existing de_DE row instead of minting a duplicate');
            self::assertTrue($filters->isEnabled(LocaleFilter::NAME), 'the finder must restore the filter it suspended for the lookup');
        } finally {
            $filters->disable(LocaleFilter::NAME);
        }
    }

    public function testGetOrTranslateDoesNotDuplicateRowWhileLocaleFilterIsActive(): void
    {
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $this->entityManager()->persist($en);
        $this->entityManager()->persist($de);
        $this->entityManager()->flush();

        $filters = $this->entityManager()->getFilters();
        $filter  = $filters->enable(LocaleFilter::NAME);
        self::assertInstanceOf(LocaleFilter::class, $filter);
        $filter->setLocale('en_US');

        try {
            $this->translator()->getOrTranslate($en, 'de_DE');
            $this->entityManager()->flush();

            // A raw row count (filter suspended) rather than findAllLocaleVariants():
            // that method groups by locale, so a duplicate (tuuid, locale) row would
            // silently overwrite the existing entry instead of showing up as a count.
            $finder = new LocaleVariantFinder($this->entityManager());
            $count  = $finder->withoutLocaleFilter(fn (): int => (int) $this->entityManager()
                ->createQueryBuilder()
                ->select('COUNT(t.id)')
                ->from(Scalar::class, 't')
                ->where('t.tuuid = :tuuid')
                ->setParameter('tuuid', (string) $tuuid)
                ->getQuery()
                ->getSingleScalarResult());

            self::assertSame(2, $count, 'getOrTranslate() must not have persisted a second de_DE row');
        } finally {
            $filters->disable(LocaleFilter::NAME);
        }
    }

    public function testTranslateOfManyToOneOwnerReusesExistingTargetVariantWhileLocaleFilterIsActive(): void
    {
        $parentTuuid = Tuuid::generate();

        $parentEn = new TranslatableOneToManyBidirectionalParent()->setLocale('en_US')->setTuuid($parentTuuid);
        $parentDe = new TranslatableOneToManyBidirectionalParent()->setLocale('de_DE')->setTuuid($parentTuuid);
        $this->entityManager()->persist($parentEn);
        $this->entityManager()->persist($parentDe);

        $child = new TranslatableManyToOneBidirectionalChild();
        $child->setLocale('en_US');
        $child->setParentSimple($parentEn);
        $this->entityManager()->persist($child);
        $this->entityManager()->flush();

        $parentDeId = $parentDe->getId();

        $filters = $this->entityManager()->getFilters();
        $filter  = $filters->enable(LocaleFilter::NAME);
        self::assertInstanceOf(LocaleFilter::class, $filter);
        $filter->setLocale('en_US');

        try {
            $translatedChild = $this->translator()->translate($child, 'de_DE');
            self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $translatedChild);

            $translatedParent = $translatedChild->getParentSimple();
            self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $translatedParent);
            self::assertSame($parentDeId, $translatedParent->getId(), 'the recursive translate() of the ManyToOne target must reuse the existing de_DE parent');
        } finally {
            $filters->disable(LocaleFilter::NAME);
        }
    }
}
