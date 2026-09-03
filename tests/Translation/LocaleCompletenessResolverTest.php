<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation;

use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Fixtures\Entity\CanNotBeNull;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\EmbeddedSharedTranslatable;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\Translatable as EmbeddedTranslatable;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\InheritedIdEntity;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\Translation\LocaleCompletenessResolver;
use Tmi\TranslationBundle\ValueObject\TranslationStatus;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class LocaleCompletenessResolverTest extends IntegrationTestCase
{
    public function testAllLocalesCompleteWhenEveryVariantMirrorsTheBaseline(): void
    {
        $tuuid = Tuuid::generate();

        foreach (['en_US' => 'EN', 'de_DE' => 'DE', 'it_IT' => 'IT'] as $locale => $title) {
            $this->entityManager()->persist(
                new Scalar()->setTuuid($tuuid)->setLocale($locale)->setTitle($title),
            );
        }
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $completeness = $this->resolver()->resolve(Scalar::class, $tuuid);

        self::assertSame($tuuid->getValue(), $completeness->tuuid()->getValue());
        self::assertSame('en_US', $completeness->baselineLocale());
        self::assertSame(
            ['en_US' => TranslationStatus::Complete, 'de_DE' => TranslationStatus::Complete, 'it_IT' => TranslationStatus::Complete],
            $completeness->statuses(),
        );
        self::assertTrue($completeness->isFullyTranslated());
        self::assertSame(['en_US', 'de_DE', 'it_IT'], $completeness->completeLocales());
        self::assertSame([], $completeness->missingLocales());
        self::assertSame([], $completeness->incompleteLocales());
        self::assertTrue($completeness->hasVariant('de_DE'));
    }

    public function testMissingAndIncompleteVariantsAreReported(): void
    {
        $tuuid = Tuuid::generate();

        $this->entityManager()->persist(
            new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN title'),
        );
        // Blank title while the baseline has one -> incomplete.
        $this->entityManager()->persist(
            new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('   '),
        );
        // No it_IT row at all -> missing.
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $completeness = $this->resolver()->resolve(Scalar::class, $tuuid);

        self::assertSame(TranslationStatus::Complete, $completeness->statusOf('en_US'));
        self::assertSame(TranslationStatus::Incomplete, $completeness->statusOf('de_DE'));
        self::assertSame(TranslationStatus::Missing, $completeness->statusOf('it_IT'));
        self::assertFalse($completeness->isFullyTranslated());
        self::assertSame(['it_IT'], $completeness->missingLocales());
        self::assertSame(['de_DE'], $completeness->incompleteLocales());
        self::assertFalse($completeness->hasVariant('it_IT'));
    }

    public function testPropertiesEmptyOnTheBaselineDoNotCountAgainstTranslations(): void
    {
        $tuuid = Tuuid::generate();

        // "empty" stays null on the baseline — an optional field. Variants
        // leaving it empty must still be complete.
        $this->entityManager()->persist(
            new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN title'),
        );
        $this->entityManager()->persist(
            new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE Titel'),
        );
        $this->entityManager()->persist(
            new Scalar()->setTuuid($tuuid)->setLocale('it_IT')->setTitle('Titolo IT'),
        );
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $completeness = $this->resolver()->resolve(Scalar::class, $tuuid);

        self::assertTrue($completeness->isFullyTranslated());
    }

    /**
     * Translatable columns declared PRIVATE on a mapped superclass must count
     * towards completeness — the property walk has to see them.
     */
    public function testInheritedPrivatePropertiesCountTowardsCompleteness(): void
    {
        $tuuid = Tuuid::generate();

        $source = new InheritedIdEntity()->setTuuid($tuuid)->setLocale('en_US');
        $source->setTitle('EN title');
        $source->setNotes('EN notes');

        // Blank inherited notes while the baseline has them -> incomplete.
        $variant = new InheritedIdEntity()->setTuuid($tuuid)->setLocale('de_DE');
        $variant->setTitle('DE Titel');
        $variant->setNotes('   ');

        $this->entityManager()->persist($source);
        $this->entityManager()->persist($variant);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $completeness = $this->resolver()->resolve(InheritedIdEntity::class, $tuuid);

        self::assertSame(TranslationStatus::Complete, $completeness->statusOf('en_US'));
        self::assertSame(TranslationStatus::Incomplete, $completeness->statusOf('de_DE'));
    }

    public function testBaselineFallsBackToFirstVariantWithoutDefaultLocale(): void
    {
        $tuuid = Tuuid::generate();

        $this->entityManager()->persist(
            new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE Titel'),
        );
        $this->entityManager()->persist(
            new Scalar()->setTuuid($tuuid)->setLocale('it_IT')->setTitle(null),
        );
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $completeness = $this->resolver()->resolve(Scalar::class, $tuuid);

        self::assertSame('de_DE', $completeness->baselineLocale());
        self::assertSame(TranslationStatus::Missing, $completeness->statusOf('en_US'));
        self::assertSame(TranslationStatus::Complete, $completeness->statusOf('de_DE'));
        self::assertSame(TranslationStatus::Incomplete, $completeness->statusOf('it_IT'));
    }

    public function testUnknownTuuidYieldsAllLocalesMissing(): void
    {
        $tuuid = Tuuid::generate();

        $completeness = $this->resolver()->resolve(Scalar::class, $tuuid);

        self::assertNull($completeness->baselineLocale());
        self::assertSame(['en_US', 'de_DE', 'it_IT'], $completeness->missingLocales());
        self::assertFalse($completeness->isFullyTranslated());
    }

    public function testBatchResolvesManyTuuidsInOneCall(): void
    {
        $complete = Tuuid::generate();
        $partial  = Tuuid::generate();
        $unknown  = Tuuid::generate();

        foreach (['en_US', 'de_DE', 'it_IT'] as $locale) {
            $this->entityManager()->persist(
                new Scalar()->setTuuid($complete)->setLocale($locale)->setTitle('Title '.$locale),
            );
        }
        $this->entityManager()->persist(
            new Scalar()->setTuuid($partial)->setLocale('en_US')->setTitle('Only EN'),
        );
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $result = $this->resolver()->resolveBatch(Scalar::class, [$complete, $partial, $unknown]);

        self::assertCount(3, $result);
        self::assertTrue($result[(string) $complete]->isFullyTranslated());
        self::assertSame(['de_DE', 'it_IT'], $result[(string) $partial]->missingLocales());
        self::assertSame(['en_US', 'de_DE', 'it_IT'], $result[(string) $unknown]->missingLocales());
    }

    public function testBatchWithNoTuuidsReturnsNothing(): void
    {
        self::assertSame([], $this->resolver()->resolveBatch(Scalar::class, []));
    }

    public function testResolveForEntityUsesTheEntitysOwnTuuid(): void
    {
        $entity = new Scalar()->setLocale('en_US')->setTitle('EN title');
        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();

        $completeness = $this->resolver()->resolveForEntity($entity);

        self::assertSame($entity->getTuuid()->getValue(), $completeness->tuuid()->getValue());
        self::assertSame(TranslationStatus::Complete, $completeness->statusOf('en_US'));
        self::assertSame(['de_DE', 'it_IT'], $completeness->missingLocales());
    }

    public function testResolverSeesAllLocalesWhileTheLocaleFilterIsActive(): void
    {
        $tuuid = Tuuid::generate();

        foreach (['en_US', 'de_DE', 'it_IT'] as $locale) {
            $this->entityManager()->persist(
                new Scalar()->setTuuid($tuuid)->setLocale($locale)->setTitle('Title '.$locale),
            );
        }
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $filters = $this->entityManager()->getFilters();
        $filter  = $filters->enable('tmi_translation_locale_filter');
        self::assertInstanceOf(LocaleFilter::class, $filter);
        $filter->setLocale('en_US');

        try {
            $completeness = $this->resolver()->resolve(Scalar::class, $tuuid);

            self::assertTrue($completeness->isFullyTranslated());
            self::assertTrue($filters->isEnabled('tmi_translation_locale_filter'));
        } finally {
            $filters->disable('tmi_translation_locale_filter');
        }
    }

    public function testEmbeddedFieldsCountOnlyNonSharedInnerProperties(): void
    {
        $tuuid = Tuuid::generate();

        // Baseline: overriddenToEmpty (class-shared embeddable, overridden by
        // #[EmptyOnTranslate]) and label (non-shared inner) are filled.
        $en = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $en->getClassShared()->setSharedByDefault('canonical')->setOverriddenToEmpty('EN note');
        $en->getPropertyShared()->setReference('REF-1')->setLabel('English label');

        // Variant: the shared inner properties differ or are empty — irrelevant.
        // The non-shared label is blank -> incomplete.
        $de = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $de->getClassShared()->setSharedByDefault(null)->setOverriddenToEmpty('DE note');
        $de->getPropertyShared()->setReference(null)->setLabel('');

        $this->entityManager()->persist($en);
        $this->entityManager()->persist($de);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $completeness = $this->resolver()->resolve(EmbeddedSharedTranslatable::class, $tuuid);

        self::assertSame(TranslationStatus::Complete, $completeness->statusOf('en_US'));
        self::assertSame(TranslationStatus::Incomplete, $completeness->statusOf('de_DE'));

        // Once the label is provided the variant is complete — proving the
        // shared inner properties (reference, sharedByDefault) never counted.
        $reloaded = $this->entityManager()->find(EmbeddedSharedTranslatable::class, $de->getId());
        self::assertInstanceOf(EmbeddedSharedTranslatable::class, $reloaded);
        $reloaded->getPropertyShared()->setLabel('Deutsches Label');
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        self::assertSame(
            TranslationStatus::Complete,
            $this->resolver()->resolve(EmbeddedSharedTranslatable::class, $tuuid)->statusOf('de_DE'),
        );
    }

    public function testNonStringValuesCountAsFilledWheneverPresent(): void
    {
        $tuuid = Tuuid::generate();

        // int/float/bool columns can never be "blank": 0, 0.0 and false are
        // values, so a variant carrying them stays complete.
        $en = new CanNotBeNull()->setEmptyNotNullable('EN')->setCount(5)->setPrice(9.99)->setActive(true);
        $en->setTuuid($tuuid)->setLocale('en_US');

        $de = new CanNotBeNull()->setEmptyNotNullable('DE')->setCount(0)->setPrice(0.0)->setActive(false);
        $de->setTuuid($tuuid)->setLocale('de_DE');

        $this->entityManager()->persist($en);
        $this->entityManager()->persist($de);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $completeness = $this->resolver()->resolve(CanNotBeNull::class, $tuuid);

        self::assertSame(TranslationStatus::Complete, $completeness->statusOf('en_US'));
        self::assertSame(TranslationStatus::Complete, $completeness->statusOf('de_DE'));
    }

    public function testWhollySharedEmbeddableIsIgnored(): void
    {
        $tuuid = Tuuid::generate();

        // sharedAddress carries #[SharedAmongstTranslations] on the entity
        // property: filled on the baseline, empty on the variant — must not
        // make the variant incomplete.
        $en = new EmbeddedTranslatable()->setTuuid($tuuid)->setLocale('en_US');
        $en->getAddress()?->setStreet('1 Main St');
        $en->getSharedAddress()?->setCity('Palermo');

        $de = new EmbeddedTranslatable()->setTuuid($tuuid)->setLocale('de_DE');
        $de->getAddress()?->setStreet('Hauptstr. 1');

        $this->entityManager()->persist($en);
        $this->entityManager()->persist($de);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $completeness = $this->resolver()->resolve(EmbeddedTranslatable::class, $tuuid);

        self::assertSame(TranslationStatus::Complete, $completeness->statusOf('en_US'));
        self::assertSame(TranslationStatus::Complete, $completeness->statusOf('de_DE'));
    }

    private function resolver(): LocaleCompletenessResolver
    {
        return new LocaleCompletenessResolver(
            $this->entityManager(),
            $this->attributeHelper(),
            'en_US',
            ['en_US', 'de_DE', 'it_IT'],
            new LocaleVariantFinder($this->entityManager()),
        );
    }
}
