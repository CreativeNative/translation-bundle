<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToManyInverseSideMapping;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalParent;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyUnidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyUnidirectionalParent;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\UnidirectionalManyToManyHandler;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(UnidirectionalManyToManyHandler::class)]
final class UnidirectionalManyToManyHandlerTest extends UnitTestCase
{
    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseWhenAttributeHelperReportsNotManyToMany(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();
        $prop   = new \ReflectionProperty($parent::class, 'simpleChildren');

        $this->attributeHelper()->method('isManyToMany')->willReturn(false);

        $args = new TranslationArgs($parent->getSimpleChildren(), 'en', 'de_DE')
            ->setProperty($prop)
            ->setTranslatedParent($parent);

        $handler = $this->createHandler();

        self::assertFalse($handler->supports($args));
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseWhenPropertyHasNoManyToManyAttribute(): void
    {
        // Anonymous class with a simple property
        $anon = new class {
            /** @var array<int, mixed> */
            public array $plain = [];
        };

        $prop = new \ReflectionProperty($anon::class, 'plain');

        // AttributeHelper reports it's ManyToMany (to reach the next check)
        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        $args = new TranslationArgs($anon->plain, 'en', 'de_DE')
            ->setProperty($prop)
            ->setTranslatedParent($anon);

        $handler = $this->createHandler();

        self::assertFalse($handler->supports($args));
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseWhenNoManyToManyAttributePresent(): void
    {
        $entity = new Scalar();
        $entity->setLocale('en');

        $prop = new \ReflectionProperty($entity::class, 'title');

        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        $args = new TranslationArgs($entity, 'en', 'de_DE')
            ->setProperty($prop)
            ->setTranslatedParent($entity);

        $handler = $this->createHandler();

        self::assertFalse($handler->supports($args));
    }

    public function testTranslateThrowsWhenNoTranslatedParent(): void
    {
        $handler = $this->createHandler();

        $args = new TranslationArgs(new ArrayCollection(), 'en', 'de_DE');
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('No translated parent provided');

        $handler->translate($args);
    }

    public function testTranslateThrowsWhenNoPropertyGiven(): void
    {
        $handler = $this->createHandler();

        $parent = new class {
            public string|null $any = null;
        };
        $args = new TranslationArgs(new ArrayCollection(), 'en', 'de_DE')
            ->setTranslatedParent($parent);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('No property given for parent of class');

        $handler->translate($args);
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateThrowsWhenAssociationNotFound(): void
    {
        $parent = new class {
            /** @var array<int, mixed>|null */
            public array|null $items = null;
        };
        $prop = new \ReflectionProperty($parent::class, 'items');

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn([]);
        $this->entityManager()->method('getClassMetadata')->with($parent::class)->willReturn($meta);

        $handler = $this->createHandler();

        $args = new TranslationArgs(new ArrayCollection(), 'en', 'de_DE')
            ->setTranslatedParent($parent)
            ->setProperty($prop);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('is not a valid association');

        $handler->translate($args);
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateThrowsWhenNotOwningSide(): void
    {
        $parent = new class {
            /** @var array<int, mixed>|null */
            public array|null $items = null;
        };
        $prop = new \ReflectionProperty($parent::class, 'items');

        $mapping = new ManyToManyInverseSideMapping(
            fieldName: 'items',
            sourceEntity: $parent::class,
            targetEntity: $parent::class,
        );
        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn([
            'items' => $mapping,
        ]);
        $this->entityManager()->method('getClassMetadata')->with($parent::class)->willReturn($meta);

        $handler = $this->createHandler();

        $args = new TranslationArgs(new ArrayCollection(), 'en', 'de_DE')
            ->setTranslatedParent($parent)
            ->setProperty($prop);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('not the owning side');

        $handler->translate($args);
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateThrowsWhenFieldNotFoundOnOwner(): void
    {
        $parent = new class {
            /** @var array<int, mixed>|null */
            public array|null $items = null;
        };
        $prop = new \ReflectionProperty($parent::class, 'items');

        $mapping = new ManyToManyOwningSideMapping(
            fieldName: 'missingField',
            sourceEntity: $parent::class,
            targetEntity: $parent::class,
        );
        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn([
            'items' => $mapping,
        ]);
        $this->entityManager()->method('getClassMetadata')->with($parent::class)->willReturn($meta);

        $handler = $this->createHandler();

        $args = new TranslationArgs(new ArrayCollection(), 'en', 'de_DE')
            ->setTranslatedParent($parent)
            ->setProperty($prop);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Field "missingField" not found in class');

        $handler->translate($args);
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateCreatesCollectionWhenFieldIsNotCollectionAndAddsTranslatedItems(): void
    {
        $parent = new class {
            /** @var iterable<int, mixed>|null */
            public iterable|null $items = null;
        };

        $prop = new \ReflectionProperty($parent::class, 'items');

        $child1 = new TranslatableManyToManyUnidirectionalChild();
        $child1->setLocale('en');
        $child2 = new TranslatableManyToManyUnidirectionalChild();
        $child2->setLocale('en');

        $data = new ArrayCollection([$child1, $child2]);

        $mapping = new ManyToManyOwningSideMapping(
            fieldName: 'items',
            sourceEntity: $parent::class,
            targetEntity: TranslatableManyToManyUnidirectionalChild::class,
        );
        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn([
            'items' => $mapping,
        ]);
        $this->entityManager()->method('getClassMetadata')->with($parent::class)->willReturn($meta);

        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        $handler = $this->createHandler();

        $args = new TranslationArgs($data, 'en', 'de_DE')
            ->setTranslatedParent($parent)
            ->setProperty($prop);

        $result = $handler->translate($args);

        self::assertCount(2, $result);
        self::assertInstanceOf(Collection::class, $parent->items);
        self::assertCount(2, $parent->items);
    }

    public function testSupportsReturnsTrueForUnidirectionalManyToManyProperty(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();

        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        // Mock the ManyToMany attribute
        $propMock = $this->createMock(\ReflectionProperty::class);
        $propMock->method('getAttributes')
            ->with(ManyToMany::class)
            ->willReturn([new class {
                /**
                 * @return array<string, mixed>
                 */
                public function getArguments(): array
                {
                    return ['targetEntity' => 'SomeClass']; // No mappedBy or inversedBy
                }
            }]);

        $propMock->method('getName')->willReturn('simpleChildren');
        $propMock->method('getDeclaringClass')->willReturn(new \ReflectionClass($parent));

        $args = new TranslationArgs($parent, 'en', 'de_DE')
            ->setProperty($propMock)
            ->setTranslatedParent($parent);

        $handler = $this->createHandler();

        self::assertTrue($handler->supports($args));
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateReplacesCollectionWithTranslatedItems(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();
        $child1 = new TranslatableManyToManyUnidirectionalChild()
            ->setLocale('en');
        $child2 = new TranslatableManyToManyUnidirectionalChild()
            ->setLocale('en');

        $parent->addSimpleChild($child1)->addSimpleChild($child2);

        $prop = new \ReflectionProperty($parent::class, 'simpleChildren');

        $mapping = new ManyToManyOwningSideMapping(
            fieldName: 'simpleChildren',
            sourceEntity: TranslatableManyToManyUnidirectionalParent::class,
            targetEntity: TranslatableManyToManyUnidirectionalChild::class,
        );
        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn([
            'simpleChildren' => $mapping,
        ]);
        $this->entityManager()->method('getClassMetadata')->with($parent::class)->willReturn($meta);

        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        $handler = $this->createHandler();

        $args = new TranslationArgs($parent->getSimpleChildren(), 'en', 'de_DE')
            ->setProperty($prop)
            ->setTranslatedParent($parent);

        $result = $handler->translate($args);

        self::assertCount(2, $result);
    }

    public function testHandleSharedAmongstTranslationsReturnsOriginalWhenNoProperty(): void
    {
        $handler = $this->createHandler();

        $args = new TranslationArgs(
            dataToBeTranslated: new ArrayCollection(),
            sourceLocale: 'en',
            targetLocale: 'de_DE',
        );

        $result = $handler->handleSharedAmongstTranslations($args);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(0, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testHandleSharedAmongstTranslationsThrowsWhenSharedAttributePresent(): void
    {
        $handler = $this->createHandler();

        $entity = new TranslatableManyToManyBidirectionalParent();
        $prop   = new \ReflectionProperty($entity::class, 'sharedChildren');

        $args = new TranslationArgs(
            dataToBeTranslated: new ArrayCollection(),
            sourceLocale: 'en',
            targetLocale: 'de_DE',
        );
        $args->setProperty($prop)->setTranslatedParent($entity);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('SharedAmongstTranslations is not allowed on unidirectional ManyToMany associations');

        $handler->handleSharedAmongstTranslations($args);
    }

    /**
     * @throws \ReflectionException
     */
    public function testHandleSharedAmongstTranslationsFallsBackToTranslate(): void
    {
        $handler = $this->createHandler();

        $parent = new TranslatableManyToManyUnidirectionalParent();
        $child  = new TranslatableManyToManyUnidirectionalChild();
        $child->setLocale('en');
        $parent->addSimpleChild($child);

        $prop = new \ReflectionProperty($parent::class, 'simpleChildren');

        $mapping = new ManyToManyOwningSideMapping(
            fieldName: 'simpleChildren',
            sourceEntity: TranslatableManyToManyUnidirectionalParent::class,
            targetEntity: TranslatableManyToManyUnidirectionalChild::class,
        );
        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn([
            'simpleChildren' => $mapping,
        ]);
        $this->entityManager()->method('getClassMetadata')->with($parent::class)->willReturn($meta);

        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        $args = new TranslationArgs($parent->getSimpleChildren(), 'en', 'de_DE')
            ->setProperty($prop)
            ->setTranslatedParent($parent);

        $result = $handler->handleSharedAmongstTranslations($args);

        self::assertCount(1, $result);
        self::assertSame($result, $parent->getSimpleChildren());
    }

    private function createHandler(): UnidirectionalManyToManyHandler
    {
        return new UnidirectionalManyToManyHandler($this->attributeHelper(), $this->translator(), $this->entityManager());
    }
}
