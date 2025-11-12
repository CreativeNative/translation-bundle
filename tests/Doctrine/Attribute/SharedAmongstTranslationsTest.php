<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\Attribute;

use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;

#[\PHPUnit\Framework\Attributes\CoversClass(SharedAmongstTranslations::class)]
final class SharedAmongstTranslationsTest extends TestCase
{
    public function testSharedAmongstTranslationsAttribute(): void
    {
        $reflection = new \ReflectionProperty(Scalar::class, 'shared');
        $attributes = $reflection->getAttributes(SharedAmongstTranslations::class);

        self::assertCount(1, $attributes);
        $attribute = $attributes[0]->newInstance();
        self::assertInstanceOf(SharedAmongstTranslations::class, $attribute);
    }
}
