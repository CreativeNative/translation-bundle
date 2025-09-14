<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use ReflectionException;
use RuntimeException;
use stdClass;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\DoctrineObjectHandler;

/**
 * @covers \Tmi\TranslationBundle\Translation\Handlers\DoctrineObjectHandler
 */
final class DoctrineObjectHandlerTest extends UnitTestCase
{
    private DoctrineObjectHandler $handler;

    public function setUp(): void
    {
        parent::setUp();

        $this->handler = new DoctrineObjectHandler(
            $this->entityManager,
            $this->translator
        );
    }

    public function testSupportsThrowsRuntimeExceptionWhenMetadataFactoryFails(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willThrowException(new RuntimeException('meta boom'));

        $this->entityManager->method('getMetadataFactory')->willReturn($metaFactory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DoctrineObjectHandler::supports: failed to determine metadata');

        $args = new TranslationArgs(new stdClass(), 'en', 'de');
        $this->handler->supports($args);
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateThrowsWhenDataIsNotObject(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DoctrineObjectHandler::translate expects an object');

        $args = new TranslationArgs('not-an-object', 'en', 'de');
        $this->handler->translate($args);
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslatePropertiesThrowsWhenDataIsNotObject(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('translateProperties expects object in TranslationArgs');

        $args = new TranslationArgs('not-an-object', 'en', 'de');
        $this->handler->translateProperties($args);
    }

    /**
     * Test the reflection fallback paths
     * @throws ReflectionException
     */
    public function testTranslatePropertiesUsesReflectionFallbackWhenAccessorThrows(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(false);
        $this->entityManager->method('getMetadataFactory')->willReturn($metaFactory);

        // Create a PropertyAccessorInterface mock that will fail both getValue and setValue
        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->method('getValue')->willThrowException(new NoSuchPropertyException('no prop'));
        $accessor->method('setValue')->willThrowException(new NoSuchPropertyException('no set'));

        // instantiate handler with our failing accessor
        $handler = new DoctrineObjectHandler($this->entityManager, $this->translator, $accessor);

        // entity with a private property 'secret'
        $entity = new class {
            private string $secret = 'orig';

            public function getSecret(): string
            {
                return $this->secret;
            }
        };

        $args = new TranslationArgs($entity, 'en', 'de');

        // Execute translate() which internally calls translateProperties()
        $result = $handler->translate($args);

        // the clone should have been returned
        $this->assertNotSame($entity, $result);
        $this->assertSame('orig', $result->getSecret());
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateSkipsNullAndEmptyCollectionProperties(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(false);
        $this->entityManager->method('getMetadataFactory')->willReturn($metaFactory);

        $entity = new class {
            public string|null $maybeNull = null;
            public Collection $emptyCollection;

            public function __construct()
            {
                $this->emptyCollection = new ArrayCollection();
            }
        };

        $args = new TranslationArgs($entity, 'en', 'de');
        $result = $this->handler->translate($args);

        $this->assertNotSame($entity, $result);
        // We don't need to count calls, just verify the behavior is correct
        $this->assertNull($result->maybeNull);
        $this->assertTrue($result->emptyCollection->isEmpty());
    }

    public function testHandleSharedAndEmptyOnTranslateReturnDefaults(): void
    {
        $obj = new stdClass();
        $args = new TranslationArgs($obj, 'en', 'de');
        $this->assertSame($obj, $this->handler->handleSharedAmongstTranslations($args));
        $this->assertNull($this->handler->handleEmptyOnTranslate($args));
    }

    public function testSupportsReturnsFalseWhenMetadataFactoryMarksTransient(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(true);

        $this->entityManager->method('getMetadataFactory')->willReturn($metaFactory);

        $args = new TranslationArgs(new stdClass(), 'en', 'de');

        self::assertFalse($this->handler->supports($args));
    }

    public function testSupportsReturnsTrueWhenManaged(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(false);

        $this->entityManager->method('getMetadataFactory')->willReturn($metaFactory);

        $args = new TranslationArgs(new stdClass(), 'en', 'de');

        self::assertTrue($this->handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateClonesAndProcessesProperties(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(false);
        $this->entityManager->method('getMetadataFactory')->willReturn($metaFactory);

        // entity with public properties that PropertyAccessor can read/set
        $entity = new class {
            public string $title = 'original';
            public string $child = 'child-value';
            public string|null $maybeNull = null;
            public Collection $emptyCollection;

            public function __construct()
            {
                $this->emptyCollection = new ArrayCollection();
            }
        };

        $args = new TranslationArgs($entity, 'en', 'de');
        $result = $this->handler->translate($args);

        // translate() must return a clone, not the same instance
        self::assertNotSame($entity, $result);
        self::assertSame('original', $result->title);
        self::assertSame('child-value', $result->child);
        self::assertNull($result->maybeNull);
        self::assertTrue($result->emptyCollection->isEmpty());
    }
}
