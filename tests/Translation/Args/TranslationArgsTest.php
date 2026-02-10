<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Args;

use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;

final class TranslationArgsTest extends TestCase
{
    public function testConstructorAndGettersWork(): void
    {
        $data   = ['foo' => 'bar'];
        $source = 'en_US';
        $target = 'de_DE';
        $args   = new TranslationArgs($data, $source, $target);
        self::assertSame($data, $args->getDataToBeTranslated());
        self::assertSame($source, $args->getSourceLocale());
        self::assertSame($target, $args->getTargetLocale());
        self::assertNull($args->getTranslatedParent());
        self::assertNull($args->getProperty());
    }

    /**
     * @throws \ReflectionException
     */
    public function testFluentSettersAndMutability(): void
    {
        $args   = new TranslationArgs(null);
        $parent = new \stdClass();
        $dummy  = new class {
            public int $prop = 42;
        };
        $property = new \ReflectionProperty($dummy::class, 'prop');
        $args
            ->setDataToBeTranslated(123)
            ->setSourceLocale('fr')
            ->setTargetLocale('it_IT')
            ->setTranslatedParent($parent)
            ->setProperty($property);
        self::assertSame(123, $args->getDataToBeTranslated());
        self::assertSame('fr', $args->getSourceLocale());
        self::assertSame('it_IT', $args->getTargetLocale());
        self::assertSame($parent, $args->getTranslatedParent());
        self::assertSame($property, $args->getProperty());
    }

    public function testNullableLocalesAllowed(): void
    {
        $args = new TranslationArgs('data', null, null);
        self::assertNull($args->getSourceLocale());
        self::assertNull($args->getTargetLocale());
        $args->setSourceLocale('en_US')->setTargetLocale('de_DE');
        self::assertSame('en_US', $args->getSourceLocale());
        self::assertSame('de_DE', $args->getTargetLocale());
    }

    public function testMixedDataAcceptsObjectsArraysScalars(): void
    {
        $args = new TranslationArgs('foo');
        self::assertSame('foo', $args->getDataToBeTranslated());
        $obj = new \stdClass();
        $args->setDataToBeTranslated($obj);
        self::assertSame($obj, $args->getDataToBeTranslated());
        $arr = ['a' => 1];
        $args->setDataToBeTranslated($arr);
        self::assertSame($arr, $args->getDataToBeTranslated());
    }

    public function testCopySourceDefaultsToNull(): void
    {
        $args = new TranslationArgs('data');
        self::assertNull($args->getCopySource());
    }

    public function testCopySourceGetterSetter(): void
    {
        $args = new TranslationArgs('data');

        $args->setCopySource(true);
        self::assertTrue($args->getCopySource());

        $args->setCopySource(false);
        self::assertFalse($args->getCopySource());

        $args->setCopySource(null);
        self::assertNull($args->getCopySource());
    }

    public function testCopySourceSetterReturnsSelf(): void
    {
        $args   = new TranslationArgs('data');
        $result = $args->setCopySource(true);
        self::assertSame($args, $result);
    }
}
