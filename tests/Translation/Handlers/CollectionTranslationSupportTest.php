<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Tmi\TranslationBundle\Translation\Handlers\CollectionTranslationSupport;

#[CoversClass(CollectionTranslationSupport::class)]
final class CollectionTranslationSupportTest extends TestCase
{
    public function testPreloadHandsTheWholeCollectionToTheTranslatorOnce(): void
    {
        $items      = [new Scalar(), new \stdClass()];
        $translator = $this->createMock(EntityTranslatorInterface::class);
        $translator->expects(self::once())->method('preload')->with($items, 'de_DE');

        CollectionTranslationSupport::preload($translator, $items, 'de_DE');
    }

    public function testPreloadIsANoOpWithoutATargetLocale(): void
    {
        $translator = $this->createMock(EntityTranslatorInterface::class);
        $translator->expects(self::never())->method('preload');

        CollectionTranslationSupport::preload($translator, [new Scalar()], null);
    }

    /**
     * The source item handed back unchanged in another locale is the in-progress guard's
     * fallback; the same item already in the target locale is a genuine existing
     * translation, and anything else is a translation result.
     */
    public function testIsCycleGuardFallbackTellsTheSourceItemFromAGenuineResult(): void
    {
        $source = new Scalar()->setLocale('en_US');

        self::assertTrue(CollectionTranslationSupport::isCycleGuardFallback($source, $source, 'de_DE'));
        self::assertFalse(CollectionTranslationSupport::isCycleGuardFallback($source, $source, 'en_US'), 'already in the target locale: a real translation');
        self::assertFalse(CollectionTranslationSupport::isCycleGuardFallback(new Scalar()->setLocale('de_DE'), $source, 'de_DE'));
    }
}
