<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Doctrine\ORM\EntityRepository;
use Ramsey\Uuid\Uuid;
use stdClass;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Tmi\TranslationBundle\Translation\Handlers\TranslatableEntityHandler;
use ReflectionException;
use Tmi\TranslationBundle\Translation\Handlers\DoctrineObjectHandler;

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
        $args = new TranslationArgs($translatable, 'en', 'de_DE');

        $this->assertTrue($this->handler->supports($args));
    }

    public function testSupportsWithNonTranslatable(): void
    {
        $nonTranslatable = new stdClass();
        $args = new TranslationArgs($nonTranslatable, 'en', 'de_DE');

        $this->assertFalse($this->handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testHandleSharedAmongstTranslations(): void
    {
        $translatable = $this->createMock(TranslatableInterface::class);
        $args = new TranslationArgs($translatable, 'en', 'de_DE');

        // Set up the mocks so that translate will return the translatable mock
        $translatable->expects($this->once())
            ->method('getTuuid')
            ->willReturn('test-uuid');

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'locale' => 'de_DE',
                'tuuid' => 'test-uuid',
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
        $args = new TranslationArgs($translatable, 'en', 'de_DE');

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

        // Set up expectations
        $originalEntity->expects($this->once())
            ->method('getTuuid')
            ->willReturn('test-uuid');

        $translationArgs = new TranslationArgs(
            $originalEntity,
            'en',
            'de_DE'
        );

        // Mock repository to return existing translation
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'locale' => 'de_DE',
                'tuuid' => 'test-uuid',
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
        $tuuid = Uuid::uuid4()->toString();

        $originalEntity = new Scalar()
            ->setTuuid($tuuid)
            ->setLocale('en');

        $translationArgs = new TranslationArgs(
            $originalEntity,
            'en',
            'de_DE'
        );

        // Mock repository to return null (no existing translation)
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'locale' => 'de_DE',
                'tuuid' => $tuuid,
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
        $this->assertEquals($tuuid, $result->getTuuid());
    }

    public function testTranslateWithReflectionException(): void
    {
        $this->expectException(ReflectionException::class);

        $originalEntity = $this->createMock(TranslatableInterface::class);

        // Set up expectations
        $originalEntity->expects($this->once())
            ->method('getTuuid')
            ->willReturn('test-uuid');

        $translationArgs = new TranslationArgs(
            $originalEntity,
            'en',
            'de_DE'
        );

        // Mock repository to return null (no existing translation)
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'locale' => 'de_DE',
                'tuuid' => 'test-uuid',
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
