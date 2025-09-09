<?php

namespace Tmi\TranslationBundle\Test\Doctrine\Attribute;

use ReflectionProperty;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations::class)]
final class SharedAmongstTranslationsTest extends TestCase
{
    public function testSharedAmongstTranslationsAttribute(): void
    {
        $reflection = new ReflectionProperty(Scalar::class, 'shared');
        $attributes = $reflection->getAttributes(SharedAmongstTranslations::class);

        self::assertCount(1, $attributes);
        $attribute = $attributes[0]->newInstance();
        self::assertInstanceOf(SharedAmongstTranslations::class, $attribute);
    }
}
