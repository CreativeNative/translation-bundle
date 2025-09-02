<?php

namespace TMI\TranslationBundle\Test\Doctrine\Attribute;

use ReflectionProperty;
use TMI\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \TMI\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations
 */
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
