<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Utils;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\Address;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\Translatable;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Utils\PropertyLocation;

#[CoversClass(PropertyLocation::class)]
final class PropertyLocationTest extends TestCase
{
    public function testAnEntityPropertyIsHeldByTheEntityItself(): void
    {
        $entity   = new Scalar();
        $location = new PropertyLocation(null, new \ReflectionProperty(Scalar::class, 'title'));

        self::assertSame($entity, $location->holderOf($entity));
    }

    public function testAnInnerEmbeddablePropertyIsHeldByTheEmbeddableInstance(): void
    {
        $entity   = new Translatable();
        $location = new PropertyLocation(
            new \ReflectionProperty(Translatable::class, 'sharedAddress'),
            new \ReflectionProperty(Address::class, 'street'),
        );

        self::assertSame($entity->getSharedAddress(), $location->holderOf($entity));
    }

    public function testAnUninitializedEmbeddedPropertyHasNoHolder(): void
    {
        $entity   = new \ReflectionClass(Translatable::class)->newInstanceWithoutConstructor();
        $location = new PropertyLocation(
            new \ReflectionProperty(Translatable::class, 'sharedAddress'),
            new \ReflectionProperty(Address::class, 'street'),
        );

        self::assertNull($location->holderOf($entity));
    }

    public function testANullEmbeddedPropertyHasNoHolder(): void
    {
        $entity = new Translatable();
        $entity->setSharedAddress(null);
        $location = new PropertyLocation(
            new \ReflectionProperty(Translatable::class, 'sharedAddress'),
            new \ReflectionProperty(Address::class, 'street'),
        );

        self::assertNull($location->holderOf($entity));
    }
}
