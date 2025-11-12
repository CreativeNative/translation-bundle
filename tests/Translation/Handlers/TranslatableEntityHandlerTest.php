<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Doctrine\ORM\EntityRepository;

use stdClass;
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Tmi\TranslationBundle\Translation\Handlers\TranslatableEntityHandler;
use ReflectionException;
use Tmi\TranslationBundle\Translation\Handlers\DoctrineObjectHandler;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class TranslatableEntityHandlerTest extends UnitTestCase
{
    private TranslatableEntityHandler $handler;

    public function setUp(): void
    {
        parent::setUp();

        // Create the real DoctrineObjectHandler with available dependencies
        $doctrineObjectHandler = new DoctrineObjectHandler(
            $this->entityManager,
            $this->translator,
            $this->propertyAccessor
        );

        $this->handler = new TranslatableEntityHandler(
            $this->entityManager,
            $doctrineObjectHandler
        );
    }

    public function testSupportsWithTranslatableInterface(): void
    {
        $translatable = $this->createMock(TranslatableInterface::class);
        $args = new TranslationArgs($translatable, 'en_US', 'de_DE');

        $this->assertTrue($this->handler->supports($args));
    }

    public function testSupportsWithNonTranslatable(): void
    {
        $nonTranslatable = new stdClass();
        $args = new TranslationArgs($nonTranslatable, 'en_US', 'de_DE');

        $this->assertFalse($this->handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testHandleSharedAmongstTranslations(): void
    {


        $translatable = $this->createMock(TranslatableInterface::class);
        $args = new TranslationArgs($translatable, 'en_US', 'de_DE');
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        // Set up the mocks so that translate will return the translatable mock
        $translatable->expects($this->once())
            ->method('getTuuid')
            ->willReturn($tuuid);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'locale' => 'de_DE',
                'tuuid' => (string) $tuuid,
            ])
            ->willReturn($translatable);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(get_class($translatable))
            ->willReturn($repository);

        $result = $this->handler->handleSharedAmongstTranslations($args);
        $this->assertSame($translatable, $result);
    }

    public function testHandleEmptyOnTranslate(): void
    {
        $translatable = $this->createMock(TranslatableInterface::class);
        $args = new TranslationArgs($translatable, 'en_US', 'de_DE');

        $result = $this->handler->handleEmptyOnTranslate($args);
        $this->assertNull($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateReturnsExistingTranslationWhenFound(): void
    {
        $existingTranslation = $this->createMock(TranslatableInterface::class);
        $originalEntity = $this->createMock(TranslatableInterface::class);

        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        // Set up expectations
        $originalEntity->expects($this->once())
            ->method('getTuuid')
            ->willReturn($tuuid);

        $translationArgs = new TranslationArgs(
            $originalEntity,
            'en_US',
            'de_DE'
        );

        // Mock repository to return existing translation
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'locale' => 'de_DE',
                'tuuid' => (string) $tuuid,
            ])
            ->willReturn($existingTranslation);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(get_class($originalEntity))
            ->willReturn($repository);

        $result = $this->handler->translate($translationArgs);

        $this->assertSame($existingTranslation, $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateCreatesNewTranslationWhenNotFound(): void
    {
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $originalEntity = new Scalar()
            ->setTuuid($tuuid)
            ->setLocale('en_US');

        $translationArgs = new TranslationArgs(
            $originalEntity,
            'en_US',
            'de_DE'
        );

        // Mock repository to return null (no existing translation)
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'locale' => 'de_DE',
                'tuuid' => (string) $tuuid,
            ])
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(get_class($originalEntity))
            ->willReturn($repository);

        // Call the method under test
        $result = $this->handler->translate($translationArgs);

        // Verify the result is a different object (cloned)
        $this->assertNotSame($originalEntity, $result);

        // Verify the locale was set correctly
        $this->assertEquals('de_DE', $result->getLocale());

        // Verify it's still the same type of object
        $this->assertInstanceOf(get_class($originalEntity), $result);

        // Verify the tuuid is preserved
        $this->assertEquals((string) $tuuid, (string) $result->getTuuid());
    }

    public function testTranslateWithReflectionException(): void
    {
        $this->expectException(ReflectionException::class);

        $originalEntity = $this->createMock(TranslatableInterface::class);

        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        // Set up expectations
        $originalEntity->expects($this->once())
            ->method('getTuuid')
            ->willReturn($tuuid);

        $translationArgs = new TranslationArgs(
            $originalEntity,
            'en_US',
            'de_DE'
        );

        // Mock repository to return null (no existing translation)
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'locale' => 'de_DE',
                'tuuid' => (string) $tuuid,
            ])
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(get_class($originalEntity))
            ->willReturn($repository);

        // Create a mock translator that throws ReflectionException
        $exceptionTranslator = $this->createMock(EntityTranslatorInterface::class);
        $exceptionTranslator->expects($this->once())
            ->method('processTranslation')
            ->willThrowException(new ReflectionException('Test exception'));

        // Create a new DoctrineObjectHandler with the exception translator
        $exceptionDoctrineObjectHandler = new DoctrineObjectHandler(
            $this->entityManager,
            $exceptionTranslator,
            $this->propertyAccessor
        );

        $exceptionHandler = new TranslatableEntityHandler(
            $this->entityManager,
            $exceptionDoctrineObjectHandler
        );

        $exceptionHandler->translate($translationArgs);
    }
}
