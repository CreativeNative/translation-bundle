<?php

namespace TMI\TranslationBundle\Test\Doctrine\Attribute;

use ReflectionProperty;
use TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use PHPUnit\Framework\TestCase;
use TMI\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;

/**
 * @coversDefaultClass \TMI\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate
 */
final class EmptyOnTranslateTest extends TestCase
{
    public function testAttributeCanBeReflected(): void
    {
        $reflection = new ReflectionProperty(Scalar::class, 'empty');
        $attributes = $reflection->getAttributes(EmptyOnTranslate::class);

        self::assertCount(1, $attributes);
        $attribute = $attributes[0]->newInstance();
        self::assertInstanceOf(EmptyOnTranslate::class, $attribute);
    }
}
