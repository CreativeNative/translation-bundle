<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine;

use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class LocaleVariantFinderTest extends IntegrationTestCase
{
    private LocaleVariantFinder $finder;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        $this->finder = new LocaleVariantFinder($this->entityManager());
    }

    public function testFindAllLocaleVariantsReturnsEveryLocaleForATuuid(): void
    {
        $em = $this->entityManager();

        $en = new Scalar()->setLocale('en_US')->setTitle('EN');
        $em->persist($en);
        $em->flush();

        $tuuid = $en->getTuuid();

        $de = $this->translator()->translate($en, 'de_DE');
        $it = $this->translator()->translate($en, 'it_IT');

        $em->persist($de);
        $em->persist($it);
        $em->flush();
        $em->clear();

        $variants = $this->finder->findAllLocaleVariants(Scalar::class, $tuuid);

        self::assertCount(3, $variants);
        self::assertArrayHasKey('en_US', $variants);
        self::assertArrayHasKey('de_DE', $variants);
        self::assertArrayHasKey('it_IT', $variants);
        $enVariant = $variants['en_US'];
        self::assertInstanceOf(Scalar::class, $enVariant);
        self::assertSame('EN', $enVariant->getTitle());
    }

    public function testFindAllLocaleVariantsReturnsEmptyArrayForAnUnknownTuuid(): void
    {
        self::assertSame([], $this->finder->findAllLocaleVariants(Scalar::class, Tuuid::generate()));
    }

    public function testFindAllLocaleVariantsBatchGroupsMultipleTuuidsByLocale(): void
    {
        $tuuid1 = Tuuid::generate();
        $tuuid2 = Tuuid::generate();

        $en1 = new Scalar()->setTuuid($tuuid1)->setLocale('en_US')->setTitle('One EN');
        $de1 = new Scalar()->setTuuid($tuuid1)->setLocale('de_DE')->setTitle('One DE');
        $en2 = new Scalar()->setTuuid($tuuid2)->setLocale('en_US')->setTitle('Two EN');

        $em = $this->entityManager();
        $em->persist($en1);
        $em->persist($de1);
        $em->persist($en2);
        $em->flush();
        $em->clear();

        $result = $this->finder->findAllLocaleVariantsBatch(Scalar::class, [$tuuid1, $tuuid2]);

        self::assertCount(2, $result);
        self::assertCount(2, $result[(string) $tuuid1]);
        self::assertCount(1, $result[(string) $tuuid2]);

        $oneEn = $result[(string) $tuuid1]['en_US'];
        $oneDe = $result[(string) $tuuid1]['de_DE'];
        self::assertInstanceOf(Scalar::class, $oneEn);
        self::assertInstanceOf(Scalar::class, $oneDe);
        self::assertSame('One EN', $oneEn->getTitle());
        self::assertSame('One DE', $oneDe->getTitle());
    }

    public function testFindAllLocaleVariantsBatchEmptyInputReturnsEmptyArray(): void
    {
        self::assertSame([], $this->finder->findAllLocaleVariantsBatch(Scalar::class, []));
    }

    public function testFindLocaleVariantReturnsTheRequestedLocale(): void
    {
        $tuuid = Tuuid::generate();
        $en    = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de    = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');

        $em = $this->entityManager();
        $em->persist($en);
        $em->persist($de);
        $em->flush();
        $em->clear();

        $variant = $this->finder->findLocaleVariant(Scalar::class, $tuuid, 'de_DE');

        self::assertInstanceOf(Scalar::class, $variant);
        self::assertSame('DE', $variant->getTitle());
    }

    public function testFindLocaleVariantReturnsNullWhenTheLocaleIsMissing(): void
    {
        $tuuid = Tuuid::generate();
        $en    = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');

        $em = $this->entityManager();
        $em->persist($en);
        $em->flush();
        $em->clear();

        self::assertNull($this->finder->findLocaleVariant(Scalar::class, $tuuid, 'it_IT'));
    }

    public function testFindLocaleVariantsBatchReturnsTheListForOneLocale(): void
    {
        $tuuid1 = Tuuid::generate();
        $tuuid2 = Tuuid::generate();

        $en1 = new Scalar()->setTuuid($tuuid1)->setLocale('en_US')->setTitle('One EN');
        $de1 = new Scalar()->setTuuid($tuuid1)->setLocale('de_DE')->setTitle('One DE');
        $en2 = new Scalar()->setTuuid($tuuid2)->setLocale('en_US')->setTitle('Two EN');

        $em = $this->entityManager();
        $em->persist($en1);
        $em->persist($de1);
        $em->persist($en2);
        $em->flush();
        $em->clear();

        $variants = $this->finder->findLocaleVariantsBatch(
            Scalar::class,
            [(string) $tuuid1, (string) $tuuid2],
            'en_US',
        );

        self::assertCount(2, $variants);

        $titles = [];
        foreach ($variants as $variant) {
            self::assertInstanceOf(Scalar::class, $variant);
            $titles[] = $variant->getTitle();
        }

        self::assertContains('One EN', $titles);
        self::assertContains('Two EN', $titles);
    }

    public function testFindLocaleVariantsBatchEmptyInputReturnsEmptyArray(): void
    {
        self::assertSame([], $this->finder->findLocaleVariantsBatch(Scalar::class, [], 'en_US'));
    }

    public function testWithoutLocaleFilterDisablesAndRestoresAnEnabledFilter(): void
    {
        $filters      = $this->entityManager()->getFilters();
        $enabledFirst = $filters->isEnabled(LocaleFilter::NAME);
        self::assertTrue($enabledFirst);

        $duringCall = $this->finder->withoutLocaleFilter(
            static fn (): bool => $filters->isEnabled(LocaleFilter::NAME),
        );

        self::assertFalse($duringCall);

        $enabledAfter = $filters->isEnabled(LocaleFilter::NAME);
        self::assertTrue($enabledAfter);
    }

    public function testWithoutLocaleFilterLeavesADisabledFilterDisabled(): void
    {
        $filters = $this->entityManager()->getFilters();
        $filters->disable(LocaleFilter::NAME);

        $duringCall = $this->finder->withoutLocaleFilter(
            static fn (): bool => $filters->isEnabled(LocaleFilter::NAME),
        );

        self::assertFalse($duringCall);
        self::assertFalse($filters->isEnabled(LocaleFilter::NAME));
    }

    public function testFindAllLocaleVariantsFindsSiblingsWhileTheFilterActivelyRestrictsToOneLocale(): void
    {
        $tuuid = Tuuid::generate();
        $en    = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $de    = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $it    = new Scalar()->setTuuid($tuuid)->setLocale('it_IT')->setTitle('IT');

        $em = $this->entityManager();
        $em->persist($en);
        $em->persist($de);
        $em->persist($it);
        $em->flush();
        $em->clear();

        $filters = $em->getFilters();
        self::assertTrue($filters->isEnabled(LocaleFilter::NAME));
        $filter = $filters->getFilter(LocaleFilter::NAME);
        self::assertInstanceOf(LocaleFilter::class, $filter);
        $filter->setLocale('en_US');

        // Sanity: the active filter really does restrict a plain query to
        // the current locale...
        $repository = $em->getRepository(Scalar::class);
        self::assertCount(1, $repository->findBy(['tuuid' => (string) $tuuid]));

        // ...but the finder still sees every variant, and leaves the filter
        // enabled again afterwards.
        $variants = $this->finder->findAllLocaleVariants(Scalar::class, $tuuid);

        self::assertCount(3, $variants);
        self::assertTrue($filters->isEnabled(LocaleFilter::NAME));
    }
}
