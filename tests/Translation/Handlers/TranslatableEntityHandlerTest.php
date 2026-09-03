<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionException;
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\InheritedIdEntity;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\PrivateIdSuperclass;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Tmi\TranslationBundle\Translation\Handlers\DoctrineObjectHandler;
use Tmi\TranslationBundle\Translation\Handlers\TranslatableEntityHandler;
use Tmi\TranslationBundle\Utils\AttributeHelper;
use Tmi\TranslationBundle\ValueObject\Tuuid;

#[AllowMockObjectsWithoutExpectations]
final class TranslatableEntityHandlerTest extends UnitTestCase
{
    private TranslatableEntityHandler $handler;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        // Create the real DoctrineObjectHandler with available dependencies
        $doctrineObjectHandler = new DoctrineObjectHandler(
            $this->entityManager(),
            $this->translator(),
            $this->propertyAccessor(),
        );

        $this->handler = new TranslatableEntityHandler(
            new LocaleVariantFinder($this->entityManager()),
            $doctrineObjectHandler,
            new AttributeHelper(),
        );
    }

    public function testSupportsWithTranslatableInterface(): void
    {
        $translatable = $this->createMock(TranslatableInterface::class);
        $context      = $this->entityContext($translatable);

        self::assertTrue($this->handler->supports($context));
    }

    public function testSupportsWithNonTranslatable(): void
    {
        $nonTranslatable = new \stdClass();
        $context         = $this->propertyContext($nonTranslatable);

        self::assertFalse($this->handler->supports($context));
    }

    /**
     * The handler ignores the shared fact entirely -- a #[SharedAmongstTranslations]
     * association still resolves via the normal get-or-create pipeline.
     *
     * @throws \ReflectionException
     */
    public function testTranslateIgnoresSharedFlag(): void
    {
        $translatable = $this->createMock(TranslatableInterface::class);
        $context      = $this->entityContext($translatable)->setShared(true);
        $tuuid        = new Tuuid(Uuid::v4()->toRfc4122());

        // Set up the mocks so that translate will return the translatable mock
        $translatable->expects($this->once())
            ->method('getTuuid')
            ->willReturn($tuuid);

        $this->entityManager()->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderReturning([$translatable]));

        $result = $this->handler->translate($context);
        self::assertSame($translatable, $result);
    }

    public function testTranslateReturnsNullWhenEmpty(): void
    {
        $translatable = $this->createMock(TranslatableInterface::class);
        $context      = $this->entityContext($translatable)->setEmpty(true);

        $result = $this->handler->translate($context);
        self::assertThat($result, self::isNull());
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateReturnsExistingTranslationWhenFound(): void
    {
        $existingTranslation = $this->createMock(TranslatableInterface::class);
        $originalEntity      = $this->createMock(TranslatableInterface::class);

        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        // Set up expectations
        $originalEntity->expects($this->once())
            ->method('getTuuid')
            ->willReturn($tuuid);

        $context = $this->entityContext($originalEntity);

        // Stub the finder's query to return the existing translation
        $this->entityManager()->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderReturning([$existingTranslation]));

        $result = $this->handler->translate($context);

        self::assertSame($existingTranslation, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateCreatesNewTranslationWhenNotFound(): void
    {
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $originalEntity = new Scalar()
            ->setTuuid($tuuid)
            ->setLocale('en_US');

        $context = $this->entityContext($originalEntity);

        // Stub the finder's query to find no existing translation
        $this->entityManager()->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderReturning([]));

        // Call the method under test
        $result = $this->handler->translate($context);

        // Verify the result is a different object (cloned)
        self::assertNotSame($originalEntity, $result);

        // Verify it's still the same type of object
        self::assertInstanceOf($originalEntity::class, $result);

        // Verify the locale was set correctly
        self::assertSame('de_DE', $result->getLocale());

        // Verify the tuuid is preserved
        self::assertSame((string) $tuuid, (string) $result->getTuuid());
    }

    public function testTranslateWithReflectionException(): void
    {
        self::expectException(\ReflectionException::class);

        $originalEntity = $this->createMock(TranslatableInterface::class);

        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        // Set up expectations
        $originalEntity->expects($this->once())
            ->method('getTuuid')
            ->willReturn($tuuid);

        $context = $this->entityContext($originalEntity);

        // Stub the finder's query to find no existing translation
        $this->entityManager()->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderReturning([]));

        // Create a mock translator that throws ReflectionException
        $exceptionTranslator = $this->createMock(EntityTranslatorInterface::class);
        $exceptionTranslator->expects($this->once())
            ->method('processTranslation')
            ->willThrowException(new \ReflectionException('Test exception'));

        // Create a new DoctrineObjectHandler with the exception translator
        $exceptionDoctrineObjectHandler = new DoctrineObjectHandler(
            $this->entityManager(),
            $exceptionTranslator,
            $this->propertyAccessor(),
        );

        $exceptionHandler = new TranslatableEntityHandler(
            new LocaleVariantFinder($this->entityManager()),
            $exceptionDoctrineObjectHandler,
            new AttributeHelper(),
        );

        $exceptionHandler->translate($context);
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateResetsGeneratedIdOnClone(): void
    {
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $originalEntity = new Scalar()
            ->setTuuid($tuuid)
            ->setLocale('en_US');

        // Simulate a persisted entity with an assigned ID
        $idProperty = new \ReflectionProperty(Scalar::class, 'id');
        $idProperty->setValue($originalEntity, 42);

        $context = $this->entityContext($originalEntity);

        // Stub the finder's query to find no existing translation
        $this->entityManager()->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderReturning([]));

        $result = $this->handler->translate($context);

        self::assertInstanceOf(Scalar::class, $result);
        self::assertNull($result->getId(), 'Generated ID must be reset to null on clone');
        self::assertSame('de_DE', $result->getLocale());
        self::assertSame((string) $tuuid, (string) $result->getTuuid());
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateResetsGeneratedIdInheritedFromMappedSuperclass(): void
    {
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $originalEntity = new InheritedIdEntity()
            ->setTuuid($tuuid)
            ->setLocale('en_US');

        // The generated id lives as a PRIVATE property on the mapped superclass —
        // ReflectionClass::getProperties() on the child class never lists it.
        $idProperty = new \ReflectionProperty(PrivateIdSuperclass::class, 'id');
        $idProperty->setValue($originalEntity, 42);

        $context = $this->entityContext($originalEntity);

        // Stub the finder's query to find no existing translation
        $this->entityManager()->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderReturning([]));

        $result = $this->handler->translate($context);

        self::assertInstanceOf(InheritedIdEntity::class, $result);
        self::assertNull($result->getId(), 'Generated id declared private on a mapped superclass must be reset to null on the clone');
        self::assertSame(42, $originalEntity->getId(), 'Source entity must keep its id');
        self::assertSame('de_DE', $result->getLocale());
        self::assertSame((string) $tuuid, (string) $result->getTuuid());
    }
}
