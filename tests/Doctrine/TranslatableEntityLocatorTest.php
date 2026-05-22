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
