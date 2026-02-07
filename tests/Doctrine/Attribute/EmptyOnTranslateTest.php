<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;

#[CoversClass(EmptyOnTranslate::class)]
final class EmptyOnTranslateTest extends TestCase
{
    public function testAttributeCanBeReflected(): void
    {
        $reflection = new \ReflectionProperty(Scalar::class, 'empty');
        $attributes = $reflection->getAttributes(EmptyOnTranslate::class);

        self::assertCount(1, $attributes);
        $attribute = $attributes[0]->newInstance();
        self::assertSame(EmptyOnTranslate::class, $attribute::class);
    }
}
