<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\Attribute;

use ReflectionProperty;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;

#[\PHPUnit\Framework\Attributes\CoversClass(\Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate::class)]
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
