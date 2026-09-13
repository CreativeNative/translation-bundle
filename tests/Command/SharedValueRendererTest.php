<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Command\SharedValueRenderer;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\Address;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Enum\Priority;
use Tmi\TranslationBundle\Fixtures\Reflection\ValueHolder;

#[CoversClass(SharedValueRenderer::class)]
final class SharedValueRendererTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function plainValues(): iterable
    {
        yield 'null' => [null, 'null'];
        yield 'true' => [true, 'true'];
        yield 'false' => [false, 'false'];
        yield 'int' => [42, '42'];
        yield 'float' => [1.5, '1.5'];
        yield 'string' => ['Sea view', '"Sea view"'];
        yield 'enum' => [Priority::High, 'Priority::High'];
        yield 'date' => [new \DateTimeImmutable('2026-09-14T10:00:00+02:00'), '2026-09-14T10:00:00+02:00'];
        yield 'array' => [['a' => 1, 'b' => ['ü']], '{"a":1,"b":["ü"]}'];
    }

    #[DataProvider('plainValues')]
    public function testRendersPlainValues(mixed $value, string $expected): void
    {
        self::assertSame($expected, $this->renderer()->render($value));
    }

    public function testALongStringIsCutAtSixtyCharactersOnACharacterBoundary(): void
    {
        $rendered = $this->renderer()->render(str_repeat('ä', 70));

        self::assertSame('"'.str_repeat('ä', 59).'…"', $rendered);
    }

    public function testAManagedEntityIsRenderedAsShortClassAndId(): void
    {
        $entity   = new Scalar();
        $metadata = self::createStub(ClassMetadata::class);
        $metadata->method('getIdentifierValues')->willReturn(['id' => 7]);

        self::assertSame('Scalar#7', $this->renderer($metadata)->render($entity));
    }

    public function testAnEntityWhoseIdIsNeitherScalarNorStringableShowsItsType(): void
    {
        $metadata = self::createStub(ClassMetadata::class);
        $metadata->method('getIdentifierValues')->willReturn(['id' => new \stdClass()]);

        self::assertSame('Scalar#stdClass', $this->renderer($metadata)->render(new Scalar()));
    }

    public function testAnEmbeddableListsItsPropertiesOneLevelDeep(): void
    {
        $address = new Address()->setStreet('Via Roma 1')->setCity('Noto');

        self::assertSame(
            'Address{street: "Via Roma 1", postalCode: null, city: "Noto…',
            $this->renderer()->render($address),
        );
    }

    public function testANestedObjectInsideAValueObjectShowsOnlyItsShortClassAndAnUninitializedPropertyReadsAsNull(): void
    {
        self::assertSame(
            'ValueHolder{address: Address, at: 2026-01-01T00:00:00+00:00…',
            $this->renderer()->render(new ValueHolder()),
        );

        $holder = new ValueHolder();
        unset($holder->address, $holder->at);
        $holder->later = 'x';

        self::assertSame('ValueHolder{address: null, at: null, priority: Priority::Lo…', $this->renderer()->render($holder));
    }

    /**
     * @param ClassMetadata<object>|null $metadata the metadata of a managed class; null makes every class transient
     */
    private function renderer(ClassMetadata|null $metadata = null): SharedValueRenderer
    {
        $factory = self::createStub(ClassMetadataFactory::class);
        $factory->method('isTransient')->willReturn(null === $metadata);

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getMetadataFactory')->willReturn($factory);
        if (null !== $metadata) {
            $entityManager->method('getClassMetadata')->willReturn($metadata);
        }

        return new SharedValueRenderer($entityManager);
    }
}
