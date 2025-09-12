<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Tmi\TranslationBundle\Translation\Handlers\DoctrineObjectHandler;

/**
 * @covers \Tmi\TranslationBundle\Translation\Handlers\DoctrineObjectHandler
 */
final class DoctrineObjectHandlerTest extends TestCase
{
    private EntityManagerInterface $em;
    private object $translatorStub; // concrete stub with processTranslation()
    private DoctrineObjectHandler $handler;

    public function setUp(): void
    {
        parent::setUp();

        // EntityManager mock — we'll make getMetadataFactory() return a ClassMetadataFactory mock
        $this->em = $this->createMock(EntityManagerInterface::class);

        // Create a translator *instance* (not a PHPUnit mock) that implements
        // EntityTranslatorInterface and also exposes processTranslation(TranslationArgs).
        // We add a public $calls counter so tests can assert how often it was used.
        $this->translatorStub = new class implements EntityTranslatorInterface {
            public int $calls = 0;

            // implement interface: translate lifecycle method for compatibility
            public function translate(TranslatableInterface $entity, string $locale): TranslatableInterface
            {
                // make a shallow clone and set locale if available
                $clone = clone $entity;
                $clone->setLocale($locale);
                return $clone;
            }

            public function afterLoad(TranslatableInterface $entity): void
            {
            }
            public function beforePersist(TranslatableInterface $entity, EntityManagerInterface $em): void
            {
            }
            public function beforeUpdate(TranslatableInterface $entity, EntityManagerInterface $em): void
            {
            }
            public function beforeRemove(TranslatableInterface $entity, EntityManagerInterface $em): void
            {
            }

            // Non-interface helper used by the handler at runtime in your codebase.
            // The handler calls $translator->processTranslation($subArgs) — so provide it here.
            public function processTranslation(TranslationArgs $args): mixed
            {
                $this->calls++;
                $data = $args->getDataToBeTranslated();
                $prop = $args->getProperty();

                // If scalar string and the property being translated is "child", uppercase it.
                if (is_string($data)) {
                    if ($prop instanceof ReflectionProperty && $prop->name === 'child') {
                        return strtoupper($data);
                    }

                    // otherwise return the string unchanged (mimic "no-op translator" for most fields)
                    return $data;
                }

                // If object, clone it and set locale if available.
                if (is_object($data)) {
                    $clone = clone $data;
                    if (method_exists($clone, 'setLocale')) {
                        $clone->setLocale($args->getTargetLocale());
                    }
                    return $clone;
                }

                // Collections or other types: return as-is
                return $data;
            }
        };

        // Build handler instance (DoctrineObjectHandler is final so use a real instance)
        $this->handler = new DoctrineObjectHandler(
            $this->em,
            $this->translatorStub // accepted — stub implements the interface
        );
    }

    public function testSupportsThrowsRuntimeExceptionWhenMetadataFactoryFails(): void
    {
        // make getMetadataFactory()->isTransient throw
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willThrowException(new RuntimeException('meta boom'));

        $this->em->method('getMetadataFactory')->willReturn($metaFactory);

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
     * Test the reflection fallback paths:
     * - PropertyAccessor::getValue throws NoSuchPropertyException -> handler reads via ReflectionProperty
     * - PropertyAccessor::setValue throws NoSuchPropertyException -> handler writes via ReflectionProperty
     *
     * This ensures both the get-fallback and set-fallback branches are covered.
     * @throws ReflectionException
     */
    public function testTranslatePropertiesUsesReflectionFallbackWhenAccessorThrows(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(false);
        $this->em->method('getMetadataFactory')->willReturn($metaFactory);

        // Create a PropertyAccessorInterface mock that will fail both getValue and setValue
        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->method('getValue')->willThrowException(new NoSuchPropertyException('no prop'));
        $accessor->method('setValue')->willThrowException(new NoSuchPropertyException('no set'));

        // instantiate handler with our failing accessor
        $handler = new DoctrineObjectHandler($this->em, $this->translatorStub, $accessor);

        // entity with a private property 'secret'
        $entity = new class {
            private string $secret = 'orig';

            // provide getter so test can assert final value
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
        // ensure translator processTranslation was called at least once
        $this->assertGreaterThanOrEqual(1, $this->translatorStub->calls);
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateSkipsNullAndEmptyCollectionProperties(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(false);
        $this->em->method('getMetadataFactory')->willReturn($metaFactory);

        $entity = new class {
            public string|null $maybeNull = null;
            public Collection $emptyCollection;

            public function __construct()
            {
                $this->emptyCollection = new ArrayCollection();
            }
        };

        // reset call counter
        $this->translatorStub->calls = 0;

        $args = new TranslationArgs($entity, 'en', 'de');
        $result = $this->handler->translate($args);

        $this->assertNotSame($entity, $result);
        $this->assertSame(0, $this->translatorStub->calls, 'processTranslation should not be called for null/empty properties');
    }

    public function testHandleSharedAndEmptyOnTranslateReturnDefaults(): void
    {
        // For DoctrineObjectHandler these are trivial/delegating defaults:
        $obj = new stdClass();
        $args = new TranslationArgs($obj, 'en', 'de');
        $this->assertSame($obj, $this->handler->handleSharedAmongstTranslations($args));
        $this->assertNull($this->handler->handleEmptyOnTranslate($args));
    }

    public function testSupportsReturnsFalseWhenMetadataFactoryMarksTransient(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(true);

        $this->em->method('getMetadataFactory')->willReturn($metaFactory);

        $args = new TranslationArgs(new stdClass(), 'en', 'de');

        self::assertFalse($this->handler->supports($args));
    }

    public function testSupportsReturnsTrueWhenManaged(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(false);

        $this->em->method('getMetadataFactory')->willReturn($metaFactory);

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
        $this->em->method('getMetadataFactory')->willReturn($metaFactory);

        // entity with public properties that PropertyAccessor can read/set
        $entity = new class {
            public string $title = 'original';
            public string $child = 'child-value';
            // null property should be skipped
            public string|null $maybeNull = null;
            // an empty collection should be skipped
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
        self::assertSame('original', $result->title, 'title unchanged if translator does not handle it');
        // child should be uppercased by our stub's processTranslation()
        self::assertSame('CHILD-VALUE', $result->child);

        // the translator.stub processTranslation() must have been called at least once
        self::assertGreaterThanOrEqual(1, $this->translatorStub->calls);
    }
}
