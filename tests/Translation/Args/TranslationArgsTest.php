<?php

declare(strict_types=1);

namespace TMI\TranslationBundle\Test\Translation\Args;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;

final class TranslationArgsTest extends TestCase
{
    public function testConstructorAndGettersWork(): void
    {
        $data   = ['foo' => 'bar'];
        $source = 'en';
        $target = 'de';
        $args = new TranslationArgs($data, $source, $target);
        self::assertSame($data, $args->getDataToBeTranslated());
        self::assertSame($source, $args->getSourceLocale());
        self::assertSame($target, $args->getTargetLocale());
        self::assertNull($args->getTranslatedParent());
        self::assertNull($args->getProperty());
    }

    public function testFluentSettersAndMutability(): void
    {
        $args = new TranslationArgs(null);
        $parent = new stdClass();
        $property = new ReflectionProperty(DummyClass::class, 'prop');
        $args
            ->setDataToBeTranslated(123)
            ->setSourceLocale('fr')
            ->setTargetLocale('it')
            ->setTranslatedParent($parent)
            ->setProperty($property);
        self::assertSame(123, $args->getDataToBeTranslated());
        self::assertSame('fr', $args->getSourceLocale());
        self::assertSame('it', $args->getTargetLocale());
        self::assertSame($parent, $args->getTranslatedParent());
        self::assertSame($property, $args->getProperty());
    }

    public function testNullableLocalesAllowed(): void
    {
        $args = new TranslationArgs('data', null, null);
        self::assertNull($args->getSourceLocale());
        self::assertNull($args->getTargetLocale());
        $args->setSourceLocale('en')->setTargetLocale('de');
        self::assertSame('en', $args->getSourceLocale());
        self::assertSame('de', $args->getTargetLocale());
    }

    public function testMixedDataAcceptsObjectsArraysScalars(): void
    {
        $args = new TranslationArgs('foo');
        self::assertSame('foo', $args->getDataToBeTranslated());
        $obj = new stdClass();
        $args->setDataToBeTranslated($obj);
        self::assertSame($obj, $args->getDataToBeTranslated());
        $arr = ['a' => 1];
        $args->setDataToBeTranslated($arr);
        self::assertSame($arr, $args->getDataToBeTranslated());
    }
}

/**
 * Helper for ReflectionProperty test.
 */
final class DummyClass
{
    public int $prop = 42;
}
