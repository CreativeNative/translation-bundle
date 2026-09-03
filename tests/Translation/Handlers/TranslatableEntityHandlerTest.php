<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionException;
use Symfony\Component\Uid\Uuid;
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
     * The catch-all for a unidirectional ManyToOne/OneToOne (no inversedBy/mappedBy):
     * none of the five dedicated association handlers' supports() match that shape, so
     * a #[SharedAmongstTranslations] association whose target is itself translatable
     * reaches this handler untreated. Rather than silently cloning the target, it
     * rejects it -- the property name from the context is named in the message.
     */
    public function testTranslateThrowsWhenShared(): void
    {
        $tuuid          = new Tuuid(Uuid::v4()->toRfc4122());
        $originalEntity = new Scalar()
            ->setTuuid($tuuid)
            ->setLocale('en_US');

        $prop    = new \ReflectionProperty(Scalar::class, 'title');
        $context = $this->entityContext($originalEntity, $prop)->setShared(true);

        $this->entityManager()->expects($this->never())->method('createQueryBuilder');

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessageMatches('/title/');

        $this->handler->translate($context);
    }

    /**
     * Defensive fallback: the property is unset (never happens on the real
     * runHandlers() path, which always sets it before marking a context shared -- see
     * EntityTranslator::runHandlers()), so the message names it "unknown" rather than
     * fail trying to read a property name off null.
     */
    public function testTranslateThrowsWhenSharedWithoutProperty(): void
    {
        $originalEntity = $this->createMock(TranslatableInterface::class);
        $context        = $this->entityContext($originalEntity)->setShared(true);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessageMatches('/unknown/');

        $this->handler->translate($context);
    }

    public function testTranslateReturnsNullWhenEmpty(): void
    {
        $translatable = $this->createMock(TranslatableInterface::class);
        $context      = $this->entityContext($translatable)->setEmpty(true);

        $result = $this->handler->translate($context);
        self::assertThat($result, self::isNull());
    }

    /**
     * Existence is resolved once, before this handler runs, by
     * EntityTranslator::processTranslation() -- not by this handler, even when an
     * existing variant is sitting right there for the EntityManager to find (see the
     * class docblock). Calling translate() directly, as this test does, always clones.
     *
     * @throws \ReflectionException
     */
    public function testTranslateNeverQueriesTheEntityManagerForAnExistingVariant(): void
    {
        $tuuid          = new Tuuid(Uuid::v4()->toRfc4122());
        $originalEntity = new Scalar()
            ->setTuuid($tuuid)
            ->setLocale('en_US');

        $context = $this->entityContext($originalEntity);

        $this->entityManager()->expects($this->never())->method('createQueryBuilder');

        $result = $this->handler->translate($context);

        self::assertNotSame($originalEntity, $result);
        self::assertInstanceOf(Scalar::class, $result);
        self::assertSame('de_DE', $result->getLocale());
        self::assertSame((string) $tuuid, (string) $result->getTuuid());
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
        $context        = $this->entityContext($originalEntity);

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

        $result = $this->handler->translate($context);

        self::assertInstanceOf(InheritedIdEntity::class, $result);
        self::assertNull($result->getId(), 'Generated id declared private on a mapped superclass must be reset to null on the clone');
        self::assertSame(42, $originalEntity->getId(), 'Source entity must keep its id');
        self::assertSame('de_DE', $result->getLocale());
        self::assertSame((string) $tuuid, (string) $result->getTuuid());
    }
}
