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

        $context = $this->propertyContext($parent->getSimpleChildren(), $prop);
        $context->setTranslatedParent($parent);

        $handler = $this->createHandler();

        self::assertFalse($handler->supports($context));
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseWhenPropertyHasNoManyToManyAttribute(): void
    {
        // Anonymous class with a collection property that carries no ManyToMany attribute
        $anon = new class {
            /** @var Collection<int, mixed> */
            public Collection $plain;

            public function __construct()
            {
                $this->plain = new ArrayCollection();
            }
        };

        $prop = new \ReflectionProperty($anon::class, 'plain');

        // AttributeHelper reports it's ManyToMany (to reach the next check)
        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        $context = $this->propertyContext($anon->plain, $prop);
        $context->setTranslatedParent($anon);

        $handler = $this->createHandler();

        self::assertFalse($handler->supports($context));
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

        // An entity itself is never a ManyToMany property's own value -- goes through
        // as an EntityTranslationContext, which the Collection check already rejects.
        $context = $this->entityContext($entity, $prop);
        $context->setTranslatedParent($entity);

        $handler = $this->createHandler();

        self::assertFalse($handler->supports($context));
    }

    public function testTranslateThrowsWhenNoTranslatedParent(): void
    {
        $handler = $this->createHandler();

        $context = $this->propertyContext(new ArrayCollection());
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('No translated parent provided');

        $handler->translate($context);
    }

    public function testTranslateThrowsWhenNoPropertyGiven(): void
    {
        $handler = $this->createHandler();

        $parent = new class {
            public string|null $any = null;
        };
        $context = $this->propertyContext(new ArrayCollection());
        $context->setTranslatedParent($parent);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('No property given for parent of class');

        $handler->translate($context);
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

        $context = $this->propertyContext(new ArrayCollection(), $prop);
        $context->setTranslatedParent($parent);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('is not a valid association');

        $handler->translate($context);
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

        $context = $this->propertyContext(new ArrayCollection(), $prop);
        $context->setTranslatedParent($parent);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('not the owning side');

        $handler->translate($context);
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

        $context = $this->propertyContext(new ArrayCollection(), $prop);
        $context->setTranslatedParent($parent);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Field "missingField" not found in class');

        $handler->translate($context);
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateReturnsAFreshCollectionWithoutTouchingTheOwnerField(): void
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

        $context = $this->propertyContext($data, $prop);
        $context->setTranslatedParent($parent);

        $result = $handler->translate($context);

        self::assertCount(2, $result);

        // The handler only produces the value; assigning it to the translated parent is the
        // caller's job, so the owner's own field must be left exactly as it was.
        self::assertNull($parent->items);
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

        // The data of a ManyToMany property is the collection, not the owning entity
        $context = $this->propertyContext($parent->getSimpleChildren(), $propMock);
        $context->setTranslatedParent($parent);

        $handler = $this->createHandler();

        self::assertTrue($handler->supports($context));
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

        $context = $this->propertyContext($parent->getSimpleChildren(), $prop);
        $context->setTranslatedParent($parent);

        $result = $handler->translate($context);

        self::assertCount(2, $result);
    }

    public function testTranslateSharedReturnsOriginalWhenNoProperty(): void
    {
        $handler = $this->createHandler();

        $context = $this->propertyContext(new ArrayCollection())->setShared(true);

        $result = $handler->translate($context);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(0, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateSharedThrowsWhenSharedAttributePresent(): void
    {
        $handler = $this->createHandler();

        $entity = new TranslatableManyToManyBidirectionalParent();
        $prop   = new \ReflectionProperty($entity::class, 'sharedChildren');

        $context = $this->propertyContext(new ArrayCollection(), $prop);
        $context->setTranslatedParent($entity);
        $context->setShared(true);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('SharedAmongstTranslations is not allowed on unidirectional ManyToMany associations');

        $handler->translate($context);
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateSharedFallsBackToNormalTranslate(): void
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

        $context = $this->propertyContext($parent->getSimpleChildren(), $prop);
        $context->setTranslatedParent($parent);
        $context->setShared(true);

        $result = $handler->translate($context);

        self::assertCount(1, $result);

        // A fresh collection, not the source one -- the source association stays intact
        self::assertNotSame($result, $parent->getSimpleChildren());
        self::assertCount(1, $parent->getSimpleChildren());
    }

    /**
     * Covers the elseif (is_iterable($sourceData)) branch.
     *
     * Passes a plain PHP array (not a Collection) as the context value so the
     * handler enters the iterable branch instead of the Collection branch.
     *
     * @throws \ReflectionException
     */
    public function testTranslateWithIterableSourceData(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();
        $child  = new TranslatableManyToManyUnidirectionalChild();
        $child->setLocale('en');

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

        // Pass a plain PHP array (iterable, but not a Collection) to hit the elseif branch
        $context = $this->propertyContext([$child], $prop);
        $context->setTranslatedParent($parent);

        $result = $handler->translate($context);

        self::assertCount(1, $result);
    }

    /**
     * Covers the non-TranslatableInterface branch: such items must be preserved,
     * not dropped. A unidirectional ManyToMany to a plain entity (tags, categories)
     * is the most common shape for this association, so silently emptying the
     * collection would be a real data loss bug.
     *
     * @throws \ReflectionException
     */
    public function testTranslatePreservesNonTranslatableItemInCollection(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();

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

        $nonTranslatable = new \stdClass();

        $context = $this->propertyContext(new ArrayCollection([$nonTranslatable]), $prop);
        $context->setTranslatedParent($parent);

        $result = $handler->translate($context);

        // The stdClass is preserved as-is, not translated and not dropped.
        self::assertCount(1, $result);
        self::assertSame($nonTranslatable, $result->first());
    }

    /**
     * Mixed collection: a TranslatableInterface item is translated, a plain
     * (non-translatable) item is preserved untouched. Both end up in the result,
     * proving the loop no longer drops one category in favour of the other.
     *
     * @throws \ReflectionException
     */
    public function testTranslatePreservesMixOfTranslatableAndNonTranslatableItems(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();
        $child  = new TranslatableManyToManyUnidirectionalChild();
        $child->setLocale('en');

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

        $tag       = new \stdClass();
        $tag->name = 'category';

        $context = $this->propertyContext(new ArrayCollection([$child, $tag]), $prop);
        $context->setTranslatedParent($parent);

        $result = $handler->translate($context);

        // Both the translatable child (routed through EntityTranslator::translate())
        // and the non-translatable tag (passed through as-is) survive -- neither
        // category is dropped in favour of the other.
        self::assertCount(2, $result);
        self::assertTrue($result->contains($tag));
        self::assertTrue($result->contains($child));
    }

    /**
     * The null-target-locale edge case mirrors BidirectionalManyToManyHandler: an
     * item is preserved untranslated rather than dropped when no target locale
     * is available to translate into.
     *
     * @throws \ReflectionException
     */
    public function testTranslatePreservesItemWhenTargetLocaleIsNull(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();
        $child  = new TranslatableManyToManyUnidirectionalChild();
        $child->setLocale('en');

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

        $context = $this->propertyContext(new ArrayCollection([$child]), $prop);
        $context->setTargetLocale(null);
        $context->setTranslatedParent($parent);

        $result = $handler->translate($context);

        self::assertCount(1, $result);
        self::assertSame($child, $result->first());
    }

    /**
     * Covers the contains() dedup guard on the preserved-as-is branch: the same
     * non-translatable instance appearing twice in the source collection must
     * only be added to the result once.
     *
     * @throws \ReflectionException
     */
    public function testTranslateDedupsRepeatedNonTranslatableItem(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();

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

        $tag = new \stdClass();

        // The same instance appears twice in the source iterable.
        $context = $this->propertyContext([$tag, $tag], $prop);
        $context->setTranslatedParent($parent);

        $result = $handler->translate($context);

        self::assertCount(1, $result);
        self::assertSame($tag, $result->first());
    }

    private function createHandler(): UnidirectionalManyToManyHandler
    {
        return new UnidirectionalManyToManyHandler($this->attributeHelper(), $this->translator(), $this->entityManager());
    }
}
