<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\Translatable;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Enum\Priority;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Handlers\ScalarHandler;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(ScalarHandler::class)]
final class ScalarHandlerTest extends UnitTestCase
{
    public function testSupportsScalars(): void
    {
        $handler = $this->createHandler();

        self::assertTrue($handler->supports($this->propertyContext('hello')));
        self::assertTrue($handler->supports($this->propertyContext(42)));
        self::assertTrue($handler->supports($this->propertyContext(null)));
        self::assertTrue($handler->supports($this->propertyContext(['a' => 1])));
    }

    /**
     * Every transient object -- a value object of any kind -- is this handler's: the
     * attribute cascade in EntityTranslator only runs inside a supporting handler, and
     * before 5.2 a DateTimeImmutable, an enum or a uid had none, so #[EmptyOnTranslate]
     * and copy_source: false were silently ignored for them.
     */
    public function testSupportsValueObjects(): void
    {
        $handler = $this->createHandler();

        self::assertTrue($handler->supports($this->propertyContext(new \DateTime())));
        self::assertTrue($handler->supports($this->propertyContext(new \DateTimeImmutable())));
        self::assertTrue($handler->supports($this->propertyContext(Priority::High)));
        self::assertTrue($handler->supports($this->propertyContext(Uuid::v7())));
        self::assertTrue($handler->supports($this->propertyContext(new \stdClass())));
    }

    public function testDoesNotSupportMappedObjectsCollectionsOrEmbeddables(): void
    {
        $handler = $this->createHandler();

        self::assertFalse($handler->supports($this->propertyContext(new Scalar())), 'a mapped entity is DoctrineObjectHandler\'s');
        self::assertFalse($handler->supports($this->propertyContext(new ArrayCollection())), 'a Collection is the collection handlers\'');

        // An embeddable is transient to Doctrine's driver, exactly like a value object;
        // the property's own mapping is what tells EmbeddedHandler's case apart.
        $address = new \ReflectionProperty(Translatable::class, 'address');
        $this->attributeHelper()->method('isEmbedded')->with($address)->willReturn(true);

        self::assertFalse($handler->supports($this->propertyContext(new \stdClass(), $address)));
    }

    public function testTranslateReturnsSameValue(): void
    {
        $handler = $this->createHandler();

        $result = $handler->translate($this->propertyContext('test-value'));

        self::assertSame('test-value', $result);
    }

    public function testTranslateReturnsSameValueWhenShared(): void
    {
        $handler = $this->createHandler();

        $context = $this->propertyContext('shared-value')->setShared(true);
        $result  = $handler->translate($context);

        self::assertSame('shared-value', $result);
    }

    public function testTranslateReturnsNullWhenEmpty(): void
    {
        $handler = $this->createHandler();

        $context = $this->propertyContext(new \DateTimeImmutable())->setEmpty(true);
        $result  = $handler->translate($context);

        self::assertThat($result, self::isNull());
    }

    /** A mutable DateTime is cloned; immutable values are handed over as they are. */
    public function testTranslateClonesAMutableDateTimeOnly(): void
    {
        $handler = $this->createHandler();

        $mutable   = new \DateTime('2020-01-01');
        $immutable = new \DateTimeImmutable('2020-01-01');

        $mutableResult = $handler->translate($this->propertyContext($mutable));
        self::assertInstanceOf(\DateTime::class, $mutableResult);
        self::assertNotSame($mutable, $mutableResult);
        self::assertEquals($mutable, $mutableResult);

        self::assertSame($immutable, $handler->translate($this->propertyContext($immutable)));
        self::assertSame(Priority::Low, $handler->translate($this->propertyContext(Priority::Low)));
    }

    private function createHandler(): ScalarHandler
    {
        // Doctrine's driver answers isTransient() with "no #[Entity] on the class":
        // true for everything except the mapped fixture.
        $factory = self::createStub(ClassMetadataFactory::class);
        $factory->method('isTransient')->willReturnCallback(static fn (string $class): bool => Scalar::class !== $class);

        $this->entityManager()->method('getMetadataFactory')->willReturn($factory);

        return new ScalarHandler($this->entityManager(), $this->attributeHelper());
    }
}
