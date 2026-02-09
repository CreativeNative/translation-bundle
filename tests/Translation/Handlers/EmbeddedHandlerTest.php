<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Fixtures\Entity\Embedded\AddressWithEmptyAndSharedProperty;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tmi\TranslationBundle\Exception\ClassLevelAttributeConflictException;
use Tmi\TranslationBundle\Exception\ValidationException;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\Address;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\ConflictClassEmbeddable;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\EmptyClassEmbeddable;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\SharedClassEmbeddable;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\DoctrineObjectHandler;
use Tmi\TranslationBundle\Translation\Handlers\EmbeddedHandler;
use Tmi\TranslationBundle\Utils\AttributeHelper;

// AddressWithEmptyAndSharedProperty uses non-standard namespace (Fixtures\Entity\Embedded)
// without PSR-4 mapping, so we need to require it explicitly for unit tests.
require_once __DIR__.'/../../Fixtures/Entity/Embedded/AddressWithEmptyAndSharedProperty.php';

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(EmbeddedHandler::class)]
final class EmbeddedHandlerTest extends UnitTestCase
{
    private EmbeddedHandler $embeddedHandler;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        // Create the real DoctrineObjectHandler (final -> cannot be mocked)
        $doctrineHandler = new DoctrineObjectHandler(
            $this->entityManager(),
            $this->translator(),
            $this->propertyAccessor(),
        );

        // Create EmbeddedHandler with mocked AttributeHelper (for existing tests)
        $this->embeddedHandler = new EmbeddedHandler(
            $this->attributeHelper(),
        );
    }

    // ---------------------------------------------------------------
    // Existing tests (updated for new constructor signature)
    // ---------------------------------------------------------------

    public function testSupportsDelegatesToAttributeHelper(): void
    {
        $this->attributeHelper()->expects($this->once())
            ->method('isEmbedded')
            ->willReturn(true);

        $obj = new class {
            public string|null $embedded = null;
        };

        $prop = new \ReflectionProperty($obj::class, 'embedded');
        $args = new TranslationArgs(null, 'en_US', 'de_DE')
            ->setProperty($prop)
            ->setTranslatedParent($obj);

        self::assertTrue($this->embeddedHandler->supports($args));
    }

    public function testHandleSharedAmongstTranslationsDelegatesToObjectHandler(): void
    {
        $data = new class {
            public string $foo = 'bar';
        };

        $args   = new TranslationArgs($data, 'en_US', 'de_DE');
        $result = $this->embeddedHandler->handleSharedAmongstTranslations($args);

        self::assertEquals(
            $data,
            $result,
            'EmbeddedHandler should delegate to DoctrineObjectHandler::handleSharedAmongstTranslations (returns same data)',
        );
    }

    // ---------------------------------------------------------------
    // Per-property resolution tests (use REAL AttributeHelper)
    // ---------------------------------------------------------------

    public function testTranslateWithMixedSharedAndEmptyProperties(): void
    {
        $realHelper = new AttributeHelper();
        $handler    = new EmbeddedHandler($realHelper);

        $address = new AddressWithEmptyAndSharedProperty();
        $address->setStreet('Test Street');
        $address->setPostalCode('12345');
        $address->setCity('Test City');
        $address->setCountry('Test Country');

        $args   = new TranslationArgs($address, 'en_US', 'de_DE');
        $result = $handler->translate($args);

        // Result is a clone, not same instance
        self::assertNotSame($address, $result);
        self::assertInstanceOf(AddressWithEmptyAndSharedProperty::class, $result);

        // $country (SharedAmongstTranslations) retains original value
        self::assertSame('Test Country', $result->getCountry());

        // $street (EmptyOnTranslate) is null
        self::assertNull($result->getStreet());

        // $noSetter (EmptyOnTranslate, no setter) is null (via reflection fallback)
        self::assertNull($result->getNoSetter());

        // $postalCode and $city (no attribute) get class default values (null)
        self::assertNull($result->getPostalCode());
        self::assertNull($result->getCity());
    }

    public function testTranslateWithClassLevelShared(): void
    {
        $realHelper = new AttributeHelper();
        $handler    = new EmbeddedHandler($realHelper);

        $embeddable = new SharedClassEmbeddable();
        $embeddable->setSharedByDefault('shared value');
        $embeddable->setOverriddenToEmpty('override value');

        $args   = new TranslationArgs($embeddable, 'en_US', 'de_DE');
        $result = $handler->translate($args);

        self::assertNotSame($embeddable, $result);
        self::assertInstanceOf(SharedClassEmbeddable::class, $result);

        // $sharedByDefault inherits class-level Shared -> retains original value
        self::assertSame('shared value', $result->getSharedByDefault());

        // $overriddenToEmpty has property-level Empty overriding class-level Shared -> null
        self::assertNull($result->getOverriddenToEmpty());
    }

    public function testTranslateWithClassLevelEmpty(): void
    {
        $realHelper = new AttributeHelper();
        $handler    = new EmbeddedHandler($realHelper);

        $embeddable = new EmptyClassEmbeddable();
        $embeddable->setEmptyByDefault('empty value');
        $embeddable->setOverriddenToShared('shared value');

        $args   = new TranslationArgs($embeddable, 'en_US', 'de_DE');
        $result = $handler->translate($args);

        self::assertNotSame($embeddable, $result);
        self::assertInstanceOf(EmptyClassEmbeddable::class, $result);

        // $emptyByDefault inherits class-level Empty -> null
        self::assertNull($result->getEmptyByDefault());

        // $overriddenToShared has property-level Shared overriding class-level Empty -> retains original value
        self::assertSame('shared value', $result->getOverriddenToShared());
    }

    public function testTranslateThrowsForClassLevelConflict(): void
    {
        $realHelper = new AttributeHelper();
        $handler    = new EmbeddedHandler($realHelper);

        $embeddable = new ConflictClassEmbeddable();
        $embeddable->setConflicted('value');

        $args = new TranslationArgs($embeddable, 'en_US', 'de_DE');

        try {
            $handler->translate($args);
            self::fail('Expected ValidationException was not thrown');
        } catch (\Throwable $e) {
            self::assertInstanceOf(ValidationException::class, $e);
            // Verify the inner error contains ClassLevelAttributeConflictException
            $errors = $e->getErrors();
            self::assertNotEmpty($errors);

            $hasConflict = false;
            foreach ($errors as $error) {
                if ($error instanceof ClassLevelAttributeConflictException) {
                    $hasConflict = true;
                }
            }
            self::assertTrue($hasConflict, 'Should contain ClassLevelAttributeConflictException');
        }
    }

    public function testTranslateWithPlainEmbeddable(): void
    {
        $realHelper = new AttributeHelper();
        $handler    = new EmbeddedHandler($realHelper);

        $address = new Address();
        $address->setStreet('Street');
        $address->setPostalCode('12345');
        $address->setCity('City');
        $address->setCountry('Country');

        $args   = new TranslationArgs($address, 'en_US', 'de_DE');
        $result = $handler->translate($args);

        // Result is a clone
        self::assertNotSame($address, $result);
        self::assertInstanceOf(Address::class, $result);

        // All properties reset to default values (null)
        self::assertNull($result->getStreet());
        self::assertNull($result->getPostalCode());
        self::assertNull($result->getCity());
        self::assertNull($result->getCountry());
    }

    // ---------------------------------------------------------------
    // Logging tests
    // ---------------------------------------------------------------

    // ---------------------------------------------------------------
    // handleEmptyOnTranslate per-property loop tests
    // ---------------------------------------------------------------

    /**
     * Covers lines 79-94: the per-property loop in handleEmptyOnTranslate().
     *
     * When the parent property does NOT have #[EmptyOnTranslate], the early return
     * at line 76 is skipped and the handler iterates each inner property:
     * - shared properties are retained (continue at line 85)
     * - empty properties are cleared (lines 88-90)
     * - the result is the clone if any property was changed (line 94)
     */
    public function testHandleEmptyOnTranslatePerPropertyResolution(): void
    {
        $realHelper = new AttributeHelper();
        $handler    = new EmbeddedHandler($realHelper);

        $address = new AddressWithEmptyAndSharedProperty();
        $address->setStreet('Test Street');
        $address->setPostalCode('12345');
        $address->setCity('Test City');
        $address->setCountry('Test Country');

        // Use a property that does NOT have #[EmptyOnTranslate] so the early return is skipped.
        // We use a dummy property from an anonymous class (no attributes at all).
        $dummy = new class {
            public string|null $prop = null;
        };
        $dummyRef = new \ReflectionProperty($dummy::class, 'prop');

        $args = new TranslationArgs($address, 'en_US', 'de_DE');
        $args->setProperty($dummyRef);

        $result = $handler->handleEmptyOnTranslate($args);

        // Result should be a clone (not the original) because $street and $noSetter have #[EmptyOnTranslate]
        self::assertNotSame($address, $result);
        self::assertInstanceOf(AddressWithEmptyAndSharedProperty::class, $result);

        // $country (#[SharedAmongstTranslations]) -> retained (continue at line 85)
        self::assertSame('Test Country', $result->getCountry());

        // $street (#[EmptyOnTranslate]) -> cleared via setter (line 89)
        self::assertNull($result->getStreet());

        // $noSetter (#[EmptyOnTranslate]) -> cleared via reflection fallback (line 89)
        self::assertNull($result->getNoSetter());

        // $postalCode and $city (no attribute) -> unchanged in clone (not cleared, not shared)
        self::assertSame('12345', $result->getPostalCode());
        self::assertSame('Test City', $result->getCity());
    }

    /**
     * Covers line 94 return path: when no inner property has #[EmptyOnTranslate],
     * $changed remains false and the original embeddable is returned (not the clone).
     */
    public function testHandleEmptyOnTranslateReturnsOriginalWhenNoPropertyChanged(): void
    {
        $realHelper = new AttributeHelper();
        $handler    = new EmbeddedHandler($realHelper);

        // Use Address fixture which has NO attributes on any property
        $address = new Address();
        $address->setStreet('Street');
        $address->setPostalCode('12345');
        $address->setCity('City');
        $address->setCountry('Country');

        // Property without #[EmptyOnTranslate] -> skip early return
        $dummy = new class {
            public string|null $prop = null;
        };
        $dummyRef = new \ReflectionProperty($dummy::class, 'prop');

        $args = new TranslationArgs($address, 'en_US', 'de_DE');
        $args->setProperty($dummyRef);

        $result = $handler->handleEmptyOnTranslate($args);

        // No properties were changed -> returns original (not clone)
        self::assertSame($address, $result);
    }

    // ---------------------------------------------------------------
    // isShared inner property tests (via handleSharedAmongstTranslations)
    // ---------------------------------------------------------------

    /**
     * Covers line 263: array_any() check for inner properties with #[SharedAmongstTranslations].
     *
     * AddressWithEmptyAndSharedProperty has:
     * - No class-level #[SharedAmongstTranslations]
     * - Property-level #[SharedAmongstTranslations] on $country
     *
     * When called without a parent property (so line 252 check is false)
     * and the class has no class-level shared attribute (line 258 check is false),
     * the array_any at line 263 finds $country and returns true.
     * handleSharedAmongstTranslations then returns the original (not a clone).
     */
    /**
     * Covers line 259: classHasSharedAmongstTranslations returns true for SharedClassEmbeddable.
     *
     * SharedClassEmbeddable has #[SharedAmongstTranslations] at the class level.
     * No parent property is set, so line 252 is skipped.
     * classHasSharedAmongstTranslations (line 258) returns true -> line 259 returns true.
     */
    public function testHandleSharedAmongstTranslationsReturnsTrueWhenClassLevelShared(): void
    {
        $realHelper = new AttributeHelper();
        $handler    = new EmbeddedHandler($realHelper);

        $embeddable = new SharedClassEmbeddable();
        $embeddable->setSharedByDefault('Test Value');

        // No parent property set -> line 252 check is false
        $args = new TranslationArgs($embeddable, 'en_US', 'de_DE');

        $result = $handler->handleSharedAmongstTranslations($args);

        // isShared returns true (class-level #[SharedAmongstTranslations]) -> returns original
        self::assertSame($embeddable, $result);
    }

    public function testHandleSharedAmongstTranslationsReturnsTrueWhenInnerPropertyIsShared(): void
    {
        $realHelper = new AttributeHelper();
        $handler    = new EmbeddedHandler($realHelper);

        $address = new AddressWithEmptyAndSharedProperty();
        $address->setStreet('Test Street');
        $address->setCountry('Test Country');

        // No parent property set -> line 252 check is false
        $args = new TranslationArgs($address, 'en_US', 'de_DE');

        $result = $handler->handleSharedAmongstTranslations($args);

        // isShared returns true (inner $country has #[SharedAmongstTranslations]) -> returns original
        self::assertSame($address, $result);
    }

    // ---------------------------------------------------------------
    // Logging tests
    // ---------------------------------------------------------------

    public function testTranslateLogsResolutionChainAtDebugLevel(): void
    {
        $realHelper = new AttributeHelper();
        $handler    = new EmbeddedHandler($realHelper);

        /** @var LoggerInterface&MockObject $mockLogger */
        $mockLogger = $this->createMock(LoggerInterface::class);
        $handler->setLogger($mockLogger);

        $embeddable = new SharedClassEmbeddable();
        $embeddable->setSharedByDefault('value');
        $embeddable->setOverriddenToEmpty('value');

        // Expect debug calls containing the [TMI Translation][Embedded] prefix
        $mockLogger->expects(self::atLeast(1))
            ->method('debug')
            ->with(
                self::stringContains('[TMI Translation][Embedded]'),
                self::anything(),
            );

        $args = new TranslationArgs($embeddable, 'en_US', 'de_DE');
        $handler->translate($args);
    }

    public function testTranslateLogsPropertyOverrideAtDebugLevel(): void
    {
        $realHelper = new AttributeHelper();
        $handler    = new EmbeddedHandler($realHelper);

        /** @var LoggerInterface&MockObject $mockLogger */
        $mockLogger = $this->createMock(LoggerInterface::class);
        $handler->setLogger($mockLogger);

        // SharedClassEmbeddable has class-level Shared and property-level Empty on overriddenToEmpty
        $embeddable = new SharedClassEmbeddable();
        $embeddable->setSharedByDefault('value');
        $embeddable->setOverriddenToEmpty('value');

        $logMessages = [];
        $mockLogger->expects(self::atLeast(1))
            ->method('debug')
            ->willReturnCallback(static function (string $message) use (&$logMessages): void {
                $logMessages[] = $message;
            });

        $args = new TranslationArgs($embeddable, 'en_US', 'de_DE');
        $handler->translate($args);

        // Check that at least one log message contains "property override"
        $hasOverrideLog = false;
        foreach ($logMessages as $msg) {
            if (str_contains($msg, 'property override')) {
                $hasOverrideLog = true;

                break;
            }
        }

        self::assertTrue($hasOverrideLog, 'Should log property override when property-level overrides class-level');
    }
}
