<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Utils;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Exception\AttributeConflictException;
use Tmi\TranslationBundle\Exception\ReadonlyPropertyException;
use Tmi\TranslationBundle\Exception\ValidationException;
use Tmi\TranslationBundle\Utils\AttributeHelper;

#[CoversClass(AttributeHelper::class)]
final class AttributeHelperTest extends TestCase
{
    private AttributeHelper $attributeHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attributeHelper = new AttributeHelper();
    }

    public function testValidatePropertyPassesForValidProperty(): void
    {
        $validClass = new class {
            public ?string $normalProperty = null;
        };
        $property = new \ReflectionProperty($validClass, 'normalProperty');

        // Should not throw
        $this->attributeHelper->validateProperty($property);

        self::assertTrue(true); // If we reach here, validation passed
    }

    public function testValidatePropertyThrowsForSharedAndEmptyConflict(): void
    {
        $conflictClass = new class {
            #[SharedAmongstTranslations]
            #[EmptyOnTranslate]
            public ?string $conflicting = null;
        };
        $property = new \ReflectionProperty($conflictClass, 'conflicting');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('TMI Translation validation failed with 1 error(s)');

        try {
            $this->attributeHelper->validateProperty($property);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(1, $errors);
            self::assertInstanceOf(AttributeConflictException::class, $errors[0]);

            throw $e;
        }
    }

    public function testValidatePropertyThrowsForReadonlyWithEmptyOnTranslate(): void
    {
        // Use eval to create a class with readonly property + EmptyOnTranslate
        // This is necessary because anonymous classes with readonly properties
        // require constructor initialization which complicates the test
        $className = 'TestReadonlyClass_'.uniqid();
        eval(<<<PHP
            class {$className} {
                #[\Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate]
                public readonly ?string \$readonlyBad;

                public function __construct() {
                    \$this->readonlyBad = null;
                }
            }
        PHP);

        $instance = new $className();
        $property = new \ReflectionProperty($instance, 'readonlyBad');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('TMI Translation validation failed with 1 error(s)');

        try {
            $this->attributeHelper->validateProperty($property);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(1, $errors);
            self::assertInstanceOf(ReadonlyPropertyException::class, $errors[0]);

            throw $e;
        }
    }

    public function testValidatePropertyCollectsMultipleErrors(): void
    {
        // Create a class with BOTH conflicts: Shared+Empty AND readonly+Empty
        $className = 'TestMultipleErrorsClass_'.uniqid();
        eval(<<<PHP
            class {$className} {
                #[\Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations]
                #[\Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate]
                public readonly ?string \$multipleConflicts;

                public function __construct() {
                    \$this->multipleConflicts = null;
                }
            }
        PHP);

        $instance = new $className();
        $property = new \ReflectionProperty($instance, 'multipleConflicts');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('TMI Translation validation failed with 2 error(s)');

        try {
            $this->attributeHelper->validateProperty($property);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(2, $errors);
            self::assertInstanceOf(AttributeConflictException::class, $errors[0]);
            self::assertInstanceOf(ReadonlyPropertyException::class, $errors[1]);

            throw $e;
        }
    }

    public function testValidatePropertyCachesResults(): void
    {
        $conflictClass = new class {
            #[SharedAmongstTranslations]
            #[EmptyOnTranslate]
            public ?string $cachedProperty = null;
        };
        $property = new \ReflectionProperty($conflictClass, 'cachedProperty');

        // First call throws
        $exceptionThrown = false;
        try {
            $this->attributeHelper->validateProperty($property);
        } catch (ValidationException $e) {
            $exceptionThrown = true;
        }
        self::assertTrue($exceptionThrown, 'First call should throw ValidationException');

        // Second call with same property should NOT throw (cached as validated)
        $this->attributeHelper->validateProperty($property);
        self::assertTrue(true); // If we reach here, second call was cached
    }

    public function testValidatePropertyLogsErrorsBeforeThrowing(): void
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $conflictClass = new class {
            #[SharedAmongstTranslations]
            #[EmptyOnTranslate]
            public ?string $loggingTest = null;
        };
        $property = new \ReflectionProperty($conflictClass, 'loggingTest');

        // Expect error log to be called with the TMI Translation prefix
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('[TMI Translation]'));

        $this->expectException(ValidationException::class);

        $this->attributeHelper->validateProperty($property, $logger);
    }

    public function testValidatePropertyDoesNotLogWhenNoErrors(): void
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $validClass = new class {
            public ?string $validProperty = null;
        };
        $property = new \ReflectionProperty($validClass, 'validProperty');

        // Logger should never be called
        $logger->expects($this->never())->method('error');

        $this->attributeHelper->validateProperty($property, $logger);
    }
}
