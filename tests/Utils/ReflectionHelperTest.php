<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Utils;

use Doctrine\Persistence\Proxy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Reflection\OneToMany\InheritedBackReferenceChild;
use Tmi\TranslationBundle\Fixtures\Reflection\OneToMany\InheritedBackReferenceSuperclass;
use Tmi\TranslationBundle\Utils\ReflectionHelper;

#[CoversClass(ReflectionHelper::class)]
final class ReflectionHelperTest extends TestCase
{
    public function testGetPropertyFindsPropertyDeclaredOnTheClassItself(): void
    {
        $class = new class {
            public string $title = 'value';
        };

        $property = ReflectionHelper::getProperty($class::class, 'title');

        self::assertSame($class::class, $property->class);
        self::assertSame('title', $property->name);
    }

    public function testGetPropertyAcceptsAReflectionClassInstance(): void
    {
        $class = new class {
            public string $title = 'value';
        };
        $reflect = new \ReflectionClass($class);

        $property = ReflectionHelper::getProperty($reflect, 'title');

        self::assertSame('title', $property->name);
    }

    /**
     * `new \ReflectionProperty($childClass, $name)` only ever looks at $childClass
     * itself and throws when a private property is declared on a parent -- this is
     * the walk that must find it there instead. Uses the real fixture the handler
     * tests exercise the bug through, rather than a synthetic hierarchy: an
     * anonymous class cannot `extends` a variable-held class name.
     */
    public function testGetPropertyWalksUpToAPrivatePropertyDeclaredOnTheParentClass(): void
    {
        $child = new InheritedBackReferenceChild();

        $property = ReflectionHelper::getProperty($child::class, 'parent');

        self::assertSame(InheritedBackReferenceSuperclass::class, $property->class, 'must resolve the declaring (parent) class, not the child');
        self::assertNull($property->getValue($child));
    }

    public function testGetPropertyThrowsWhenPropertyExistsNowhereInTheHierarchy(): void
    {
        $child = new InheritedBackReferenceChild();

        self::expectException(\ReflectionException::class);
        self::expectExceptionMessage(sprintf('Property "missing" does not exist on class "%s" or any of its parent classes.', $child::class));

        ReflectionHelper::getProperty($child::class, 'missing');
    }

    /**
     * PHP never memoizes ReflectionProperty instances internally -- two
     * unrelated ReflectionClass::getProperties() calls for the same class
     * return equal but distinct objects (verified while building this
     * cache), so `===` identity between two getHierarchyProperties() calls
     * is proof the second one was served from the cache, not a fresh walk.
     */
    public function testGetHierarchyPropertiesMemoizesPerClass(): void
    {
        $first  = ReflectionHelper::getHierarchyProperties(new \ReflectionClass(Scalar::class));
        $second = ReflectionHelper::getHierarchyProperties(new \ReflectionClass(Scalar::class));

        self::assertSame($first, $second);
    }

    /**
     * A classic (non-native-lazy-object) Doctrine proxy subclasses the real
     * entity; reflecting the proxy class directly must resolve to the same
     * cache entry as the real class, not a separate one keyed by the
     * generated subclass name -- the same proxy-unwrapping precedent
     * EntityTranslator::resolveCopySource() and DoctrineObjectHandler::
     * supports() apply (WP7 #16).
     */
    public function testGetHierarchyPropertiesUnwrapsAProxyToTheRealClasssCacheEntry(): void
    {
        $proxy = new class extends Scalar implements Proxy {
            public function __load(): void
            {
            }

            public function __isInitialized(): bool
            {
                return true;
            }
        };

        $direct   = ReflectionHelper::getHierarchyProperties(new \ReflectionClass(Scalar::class));
        $viaProxy = ReflectionHelper::getHierarchyProperties(new \ReflectionClass($proxy));

        self::assertSame($direct, $viaProxy);
    }
}
