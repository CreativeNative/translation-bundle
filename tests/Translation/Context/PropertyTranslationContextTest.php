<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Translation\Context\PropertyTranslationContext;

#[CoversClass(PropertyTranslationContext::class)]
final class PropertyTranslationContextTest extends TestCase
{
    public function testConstructorAndGettersWork(): void
    {
        $data   = ['foo' => 'bar'];
        $source = 'en_US';
        $target = 'de_DE';

        $context = new PropertyTranslationContext($data, $source, $target);

        self::assertSame($data, $context->getValue());
        self::assertSame($data, $context->getSubject());
        self::assertSame($source, $context->getSourceLocale());
        self::assertSame($target, $context->getTargetLocale());
        self::assertNull($context->getTranslatedParent());
        self::assertNull($context->getProperty());
        self::assertNull($context->getCopySource());
        self::assertFalse($context->isShared());
        self::assertFalse($context->isEmpty());
    }

    /**
     * @throws \ReflectionException
     */
    public function testFluentSettersAndMutability(): void
    {
        $context = new PropertyTranslationContext(null);
        $parent  = new \stdClass();
        $dummy   = new class {
            public int $prop = 42;
        };
        $property = new \ReflectionProperty($dummy::class, 'prop');

        $context
            ->setSourceLocale('fr')
            ->setTargetLocale('it_IT')
            ->setTranslatedParent($parent)
            ->setProperty($property)
            ->setCopySource(true)
            ->setShared(true)
            ->setEmpty(true);

        self::assertSame('fr', $context->getSourceLocale());
        self::assertSame('it_IT', $context->getTargetLocale());
        self::assertSame($parent, $context->getTranslatedParent());
        self::assertSame($property, $context->getProperty());
        self::assertTrue($context->getCopySource());
        self::assertTrue($context->isShared());
        self::assertTrue($context->isEmpty());
    }

    public function testNullableLocalesAllowed(): void
    {
        $context = new PropertyTranslationContext('data', null, null);
        self::assertNull($context->getSourceLocale());
        self::assertNull($context->getTargetLocale());
        $context->setSourceLocale('en_US')->setTargetLocale('de_DE');
        self::assertSame('en_US', $context->getSourceLocale());
        self::assertSame('de_DE', $context->getTargetLocale());
    }

    public function testMixedValueAcceptsAScalar(): void
    {
        $context = new PropertyTranslationContext('foo');
        self::assertSame('foo', $context->getValue());
    }

    public function testMixedValueAcceptsAnObjectAndExposesItAsTheSubject(): void
    {
        $context = new PropertyTranslationContext('foo');

        $obj = new \stdClass();
        $context->setSubject($obj);

        self::assertSame($obj, $context->getValue());
        self::assertSame($obj, $context->getSubject());
    }

    public function testMixedValueAcceptsAnArray(): void
    {
        $context = new PropertyTranslationContext('foo');

        $arr = ['a' => 1];
        $context->setSubject($arr);

        self::assertSame($arr, $context->getValue());
    }

    public function testSetSubjectReturnsSelf(): void
    {
        $context = new PropertyTranslationContext('data');
        $result  = $context->setSubject('other');
        self::assertSame($context, $result);
    }

    public function testCopySourceDefaultsToNull(): void
    {
        $context = new PropertyTranslationContext('data');
        self::assertNull($context->getCopySource());
    }

    public function testCopySourceGetterSetter(): void
    {
        $context = new PropertyTranslationContext('data');

        $context->setCopySource(true);
        self::assertTrue($context->getCopySource());

        $context->setCopySource(false);
        self::assertFalse($context->getCopySource());

        $context->setCopySource(null);
        self::assertNull($context->getCopySource());
    }

    public function testCopySourceSetterReturnsSelf(): void
    {
        $context = new PropertyTranslationContext('data');
        $result  = $context->setCopySource(true);
        self::assertSame($context, $result);
    }
}
