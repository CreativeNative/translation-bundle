<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Context\TranslationContext;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Tmi\TranslationBundle\Translation\Handlers\DoctrineObjectHandler;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(DoctrineObjectHandler::class)]
final class DoctrineObjectHandlerTest extends UnitTestCase
{
    private DoctrineObjectHandler $handler;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        $this->handler = new DoctrineObjectHandler(
            $this->entityManager(),
            $this->translator(),
        );
    }

    public function testSupportsReturnsFalseWhenDataIsNotObject(): void
    {
        $context = $this->propertyContext('a string');

        self::assertFalse($this->handler->supports($context));
    }

    public function testSupportsThrowsRuntimeExceptionWhenMetadataFactoryFails(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willThrowException(new \RuntimeException('meta boom'));

        $this->entityManager()->method('getMetadataFactory')->willReturn($metaFactory);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('DoctrineObjectHandler::supports: failed to determine metadata');

        $context = $this->propertyContext(new \stdClass());
        $this->handler->supports($context);
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateThrowsWhenDataIsNotObject(): void
    {
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('DoctrineObjectHandler::translate expects an object');

        $context = $this->propertyContext('not-an-object');
        $this->handler->translate($context);
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslatePropertiesThrowsWhenDataIsNotObject(): void
    {
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('translateProperties expects an object as the context subject');

        $context = $this->propertyContext('not-an-object');
        $this->handler->translateProperties($context);
    }

    public function testTranslateReturnsIdentityWhenShared(): void
    {
        $obj     = new \stdClass();
        $context = $this->propertyContext($obj)->setShared(true);

        self::assertSame($obj, $this->handler->translate($context));
    }

    public function testTranslateReturnsNullWhenEmpty(): void
    {
        $context = $this->propertyContext(new \stdClass())->setEmpty(true);

        self::assertThat($this->handler->translate($context), self::isNull());
    }

    /**
     * Test the reflection fallback paths.
     *
     * @throws \ReflectionException
     */
    public function testTranslatePropertiesUsesReflectionFallbackWhenAccessorThrows(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(false);
        $this->entityManager()->method('getMetadataFactory')->willReturn($metaFactory);

        // Create a PropertyAccessorInterface mock that will fail both getValue and setValue
        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->method('getValue')->willThrowException(new NoSuchPropertyException('no prop'));
        $accessor->method('setValue')->willThrowException(new NoSuchPropertyException('no set'));

        // instantiate handler with our failing accessor
        $handler = new DoctrineObjectHandler($this->entityManager(), $this->translator(), $accessor);

        // entity with a private property 'secret'
        $entity = new class {
            private string $secret = 'orig';

            public function getSecret(): string
            {
                return $this->secret;
            }
        };

        $context = $this->propertyContext($entity);

        // Execute translate() which internally calls translateProperties()
        $result = $handler->translate($context);

        // the clone should have been returned with original value preserved
        self::assertNotSame($entity, $result);
        self::assertInstanceOf($entity::class, $result);
        self::assertSame('orig', $result->getSecret());
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateSkipsNullAndEmptyCollectionProperties(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(false);
        $this->entityManager()->method('getMetadataFactory')->willReturn($metaFactory);

        $entity = new class {
            public string|null $maybeNull = null;
            /** @var Collection<int, mixed> */
            public Collection $emptyCollection;

            public function __construct()
            {
                $this->emptyCollection = new ArrayCollection();
            }
        };

        $context = $this->propertyContext($entity);
        $result  = $this->handler->translate($context);

        self::assertNotSame($entity, $result);
        self::assertInstanceOf($entity::class, $result);
        // Verify cloned object preserves null and empty collection values
        self::assertNull($result->maybeNull);
        self::assertTrue($result->emptyCollection->isEmpty());
    }

    /**
     * #17 negative proof: `clone $data` is shallow -- without a fresh collection on
     * the clone, the property still points at the exact same (empty) Collection
     * object as the source's. Mutating the clone's collection would silently
     * mutate the source's too, since they are literally the same object.
     *
     * @throws \ReflectionException
     */
    public function testTranslateGivesEmptyCollectionPropertyAFreshInstance(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(false);
        $this->entityManager()->method('getMetadataFactory')->willReturn($metaFactory);

        $entity = new class {
            /** @var Collection<int, mixed> */
            public Collection $items;

            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };

        $result = $this->handler->translate($this->propertyContext($entity));

        self::assertInstanceOf($entity::class, $result);
        self::assertNotSame($entity->items, $result->items);

        $result->items->add('added-on-the-clone');

        self::assertTrue($entity->items->isEmpty(), 'mutating the clone must not leak back into the source collection');
    }

    /**
     * A readonly Collection property is already initialised on the clone and PHP
     * rejects every write to it -- the #17 fix must skip writing there instead of
     * throwing, same as every other readonly property further down.
     *
     * @throws \ReflectionException
     */
    public function testTranslateSkipsFreshCollectionForReadonlyEmptyCollectionProperty(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(false);
        $this->entityManager()->method('getMetadataFactory')->willReturn($metaFactory);

        $entity = new class {
            /** @var Collection<int, mixed> */
            public readonly Collection $items;

            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };

        $result = $this->handler->translate($this->propertyContext($entity));

        self::assertInstanceOf($entity::class, $result);
        self::assertSame($entity->items, $result->items);
    }

    public function testSupportsReturnsFalseWhenMetadataFactoryMarksTransient(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(true);

        $this->entityManager()->method('getMetadataFactory')->willReturn($metaFactory);

        $context = $this->propertyContext(new \stdClass());

        self::assertFalse($this->handler->supports($context));
    }

    public function testSupportsReturnsTrueWhenManaged(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(false);

        $this->entityManager()->method('getMetadataFactory')->willReturn($metaFactory);

        $context = $this->propertyContext(new \stdClass());

        self::assertTrue($this->handler->supports($context));
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslatePropertiesPropagatesCopySourceTrue(): void
    {
        // Use a mock translator to capture sub-contexts
        $mockTranslator  = $this->createMock(EntityTranslatorInterface::class);
        $handlerWithMock = new DoctrineObjectHandler(
            $this->entityManager(),
            $mockTranslator,
        );

        $capturedContexts = [];
        $mockTranslator->method('processTranslation')
            ->willReturnCallback(function (TranslationContext $subContext) use (&$capturedContexts): mixed {
                $capturedContexts[] = $subContext;

                return $subContext->getSubject();
            });

        $entity = new class {
            public string $title = 'original';
        };

        $context = $this->propertyContext($entity);
        $context->setCopySource(true);

        $handlerWithMock->translateProperties($context);

        self::assertNotEmpty($capturedContexts);
        self::assertTrue($capturedContexts[0]->getCopySource());
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslatePropertiesPropagatesCopySourceFalse(): void
    {
        // Use a mock translator to capture sub-contexts
        $mockTranslator  = $this->createMock(EntityTranslatorInterface::class);
        $handlerWithMock = new DoctrineObjectHandler(
            $this->entityManager(),
            $mockTranslator,
        );

        $capturedContexts = [];
        $mockTranslator->method('processTranslation')
            ->willReturnCallback(function (TranslationContext $subContext) use (&$capturedContexts): mixed {
                $capturedContexts[] = $subContext;

                return $subContext->getSubject();
            });

        $entity = new class {
            public string $title = 'original';
        };

        $context = $this->propertyContext($entity);
        $context->setCopySource(false);

        $handlerWithMock->translateProperties($context);

        self::assertNotEmpty($capturedContexts);
        self::assertFalse($capturedContexts[0]->getCopySource());
    }

    /**
     * A readonly property is already initialised on the clone, so PHP rejects every write to
     * it — including one that would store the identical value. Skipping keeps translation of
     * readonly (typically shared) properties working instead of crashing with a raw Error.
     *
     * @throws \ReflectionException
     */
    public function testTranslatePropertiesSkipsUnchangedReadonlyProperty(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(false);
        $this->entityManager()->method('getMetadataFactory')->willReturn($metaFactory);

        $entity = new class {
            public readonly string $sku;

            public function __construct()
            {
                $this->sku = 'SKU-1';
            }
        };

        $result = $this->handler->translate($this->propertyContext($entity));

        self::assertNotSame($entity, $result);
        self::assertInstanceOf($entity::class, $result);
        self::assertSame('SKU-1', $result->sku);
    }

    /**
     * Same for a readonly object value that a handler hands back as an equal clone: nothing
     * would change, so there is nothing to write.
     *
     * @throws \ReflectionException
     */
    public function testTranslatePropertiesSkipsReadonlyPropertyResolvedToAnEqualClone(): void
    {
        $mockTranslator = $this->createMock(EntityTranslatorInterface::class);
        $mockTranslator->method('processTranslation')->willReturnCallback(
            static function (TranslationContext $subContext): mixed {
                $value = $subContext->getSubject();

                return is_object($value) ? clone $value : $value;
            },
        );

        $handler = new DoctrineObjectHandler($this->entityManager(), $mockTranslator);

        $entity = new class {
            public readonly \DateTimeImmutable $createdAt;

            public function __construct()
            {
                $this->createdAt = new \DateTimeImmutable('2026-01-01 00:00:00');
            }
        };

        $handler->translateProperties($this->propertyContext($entity));

        self::assertEquals(new \DateTimeImmutable('2026-01-01 00:00:00'), $entity->createdAt);
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslatePropertiesThrowsWhenReadonlyPropertyWouldChange(): void
    {
        $mockTranslator = $this->createMock(EntityTranslatorInterface::class);
        $mockTranslator->method('processTranslation')->willReturn('a different value');

        $handler = new DoctrineObjectHandler($this->entityManager(), $mockTranslator);

        $entity = new class {
            public readonly string $sku;

            public function __construct()
            {
                $this->sku = 'SKU-1';
            }
        };

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('is readonly and cannot be reassigned while translating');

        $handler->translateProperties($this->propertyContext($entity));
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateClonesAndProcessesProperties(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(false);
        $this->entityManager()->method('getMetadataFactory')->willReturn($metaFactory);

        // entity with public properties that PropertyAccessor can read/set
        $entity = new class {
            public string $title          = 'original';
            public string $child          = 'child-value';
            public string|null $maybeNull = null;
            /** @var Collection<int, mixed> */
            public Collection $emptyCollection;

            public function __construct()
            {
                $this->emptyCollection = new ArrayCollection();
            }
        };

        $context = $this->propertyContext($entity);
        $result  = $this->handler->translate($context);

        // translate() must return a clone, not the same instance
        self::assertNotSame($entity, $result);
        self::assertInstanceOf($entity::class, $result);
        // Verify cloned properties retain their original values
        self::assertSame('original', $result->title);
        self::assertSame('child-value', $result->child);
        self::assertNull($result->maybeNull);
        self::assertTrue($result->emptyCollection->isEmpty());
    }
}
