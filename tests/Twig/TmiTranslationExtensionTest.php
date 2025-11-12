<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalParent;
use Tmi\TranslationBundle\Twig\TmiTranslationExtension;
use Twig\TwigTest;

final class TmiTranslationExtensionTest extends TestCase
{
    public function testGetTestsReturnsTwigTestForTranslatableInterface(): void
    {
        $locales   = ['en_US', 'it_IT'];
        $extension = new TmiTranslationExtension($locales);

        $tests = $extension->getTests();
        self::assertCount(1, $tests);
        self::assertInstanceOf(TwigTest::class, $tests[0]);

        $translatable    = new TranslatableOneToOneBidirectionalParent();
        $nonTranslatable = new \stdClass();

        // TwigTest callback
        $callback = $tests[0]->getCallable();

        self::assertTrue($callback($translatable));
        self::assertFalse($callback($nonTranslatable));
    }

    public function testGetGlobalsReturnsLocalesArray(): void
    {
        $locales   = ['en_US', 'it_IT'];
        $extension = new TmiTranslationExtension($locales);

        $globals = $extension->getGlobals();
        self::assertArrayHasKey('locales', $globals);
        self::assertSame($locales, $globals['locales']);
    }
}
