<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PHPUnit\Framework\Attributes\CoversClass;
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
