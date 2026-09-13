<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Doctrine\TranslatableEntityLocator;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti\StiBook;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti\StiRoot;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti\StiToy;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;

#[CoversClass(TranslatableEntityLocator::class)]
final class TranslatableEntityLocatorTest extends TestCase
{
    public function testLocateReturnsTranslatableEntitiesOnly(): void
    {
        $translatable = $this->metadata(Scalar::class);

        $superclass                     = $this->metadata(Scalar::class);
        $superclass->isMappedSuperclass = true;

        $nonTranslatable = $this->metadata(\stdClass::class);

        $locator = new TranslatableEntityLocator(
            $this->entityManagerWith([$nonTranslatable, $translatable, $superclass]),
        );

        self::assertSame([Scalar::class], $locator->locate());
    }

    public function testLocateReturnsEmptyWhenNoTranslatableEntities(): void
    {
        $locator = new TranslatableEntityLocator(
            $this->entityManagerWith([$this->metadata(\stdClass::class)]),
        );

        self::assertSame([], $locator->locate());
    }

    /**
     * A SINGLE_TABLE (or JOINED) hierarchy maps every concrete subclass to its
     * own ClassMetadata, but querying the root is already polymorphic — every
     * concrete row comes back from that one query. Listing subclasses too
     * would make every consumer of locate() (doctor, sync-shared) walk the
     * same physical rows once per subclass on top of the root's own pass.
     */
    public function testLocateReturnsOnlyTheRootOfAnInheritanceHierarchy(): void
    {
        $root = $this->metadata(StiRoot::class);

        $book                 = $this->metadata(StiBook::class);
        $book->rootEntityName = StiRoot::class;

        $toy                 = $this->metadata(StiToy::class);
        $toy->rootEntityName = StiRoot::class;

        // Order deliberately does not put the root first, to prove the filter
        // is not relying on iteration order.
        $locator = new TranslatableEntityLocator(
            $this->entityManagerWith([$book, $toy, $root]),
        );

        self::assertSame([StiRoot::class], $locator->locate());
    }

    /**
     * isTranslatableEntity() is the `--entity` test of every command: it accepts a
     * concrete STI leaf the locate() list never names, and refuses a class that
     * does not exist, a class Doctrine has no mapping for, a mapped superclass
     * and a mapped class that is not translatable.
     */
    #[DataProvider('entityOptionValues')]
    public function testIsTranslatableEntityAcceptsAMappedTranslatableClassOnly(string $class, bool $expected): void
    {
        $superclass                     = $this->metadata(Scalar::class);
        $superclass->isMappedSuperclass = true;

        $leaf                 = $this->metadata(StiBook::class);
        $leaf->rootEntityName = StiRoot::class;

        // What Doctrine "knows": the key is the class name asked for, the value
        // the metadata it answers with. \DateTimeImmutable stands in for a mapped
        // superclass, \stdClass for a mapped but non-translatable entity.
        $byClass = [
            Scalar::class             => $this->metadata(Scalar::class),
            StiBook::class            => $leaf,
            \stdClass::class          => $this->metadata(\stdClass::class),
            \DateTimeImmutable::class => $superclass,
        ];

        $factory = self::createStub(ClassMetadataFactory::class);
        $factory->method('isTransient')->willReturnCallback(static fn (string $class): bool => !isset($byClass[$class]));

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getMetadataFactory')->willReturn($factory);
        $entityManager->method('getClassMetadata')->willReturnCallback(static fn (string $class): ClassMetadata => $byClass[$class]);

        self::assertSame($expected, new TranslatableEntityLocator($entityManager)->isTranslatableEntity($class));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function entityOptionValues(): iterable
    {
        yield 'a translatable entity' => [Scalar::class, true];
        yield 'a concrete STI leaf locate() does not name' => [StiBook::class, true];
        yield 'a class that does not exist' => ['App\\Entity\\DoesNotExist', false];
        yield 'a real class Doctrine has no mapping for' => [\ArrayObject::class, false];
        yield 'a mapped superclass' => [\DateTimeImmutable::class, false];
        yield 'a mapped class that is not translatable' => [\stdClass::class, false];
    }

    /**
     * @param class-string $class
     *
     * @return ClassMetadata<object>
     */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $metadata->initializeReflection(new RuntimeReflectionService());

        return $metadata;
    }

    /**
     * @param list<ClassMetadata<object>> $allMetadata
     */
    private function entityManagerWith(array $allMetadata): EntityManagerInterface
    {
        $factory = self::createStub(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willReturn($allMetadata);

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getMetadataFactory')->willReturn($factory);

        return $entityManager;
    }
}
