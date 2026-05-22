<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Exception\OrphanTranslationException;

#[CoversClass(OrphanTranslationException::class)]
final class OrphanTranslationExceptionTest extends TestCase
{
    public function testExtendsLogicException(): void
    {
        $exception = OrphanTranslationException::forEntity('App\Entity\Page', 'de_DE');

        $parents = class_parents($exception);
        self::assertNotEmpty($parents);
        self::assertContains(\LogicException::class, $parents);
    }

    public function testForEntityMentionsClassAndLocale(): void
    {
        $exception = OrphanTranslationException::forEntity('App\Entity\Page', 'de_DE');

        self::assertStringContainsString('App\Entity\Page', $exception->getMessage());
        self::assertStringContainsString('de_DE', $exception->getMessage());
        self::assertStringContainsString('EntityTranslator::translate()', $exception->getMessage());
        self::assertStringContainsString('strict_orphan_check', $exception->getMessage());
    }
}
