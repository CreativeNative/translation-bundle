<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\OneToManyAssociationMapping;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalParent;
use Tmi\TranslationBundle\Fixtures\Reflection\OneToMany\InheritedBackReferenceChild;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Handlers\BidirectionalOneToManyHandler;

#[AllowMockObjectsWithoutExpectations]
final class BidirectionalOneToManyHandlerTest extends UnitTestCase
{
    /** ------------------------- Supports -------------------------.
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseWhenNotOneToMany(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToManyBidirectionalParent();
        $prop    = new \ReflectionProperty($entity, 'simpleChildren');

        $context = $this->propertyContext($entity->getSimpleChildren(), $prop);

        $this->attributeHelper()->method('isOneToMany')->with($prop)->willReturn(false);

        self::assertFalse($handler->supports($context));
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsTrueWhenOneToManyWithMappedBy(): void
    {
        $handler = $this->createHandler();

        $entity = new TranslatableOneToManyBidirectionalParent();
        $prop   = new \ReflectionProperty($entity, 'simpleChildren');

        // The data of a OneToMany property is the children collection, not the parent entity
        $context = $this->propertyContext($entity->getSimpleChildren(), $prop);

        $this->attributeHelper()->method('isOneToMany')->with($prop)->willReturn(true);

        self::assertTrue($handler->supports($context));
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseWhenNotPropertyContext(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToManyBidirectionalParent();

        // An EntityTranslationContext never carries a Collection as its subject.
        $context = $this->entityContext($entity);

        self::assertFalse($handler->supports($context));
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseWhenNoOneToManyAttributes(): void
    {
        $handler = $this->createHandler();

        // Use a real Translatable entity
        $parent = new TranslatableOneToManyBidirectionalParent();

        // Pick a property that exists but does NOT have a OneToMany attribute
        $prop = new \ReflectionProperty($parent::class, 'title');
        // NOTE: "notACollection" should be a real property on the entity that is NOT a OneToMany

        // Mock attribute helper to return true for isOneToMany
        $this->attributeHelper()->method('isOneToMany')->with($prop)->willReturn(true);

        // Collection data gets past the first guard, so the missing attribute is what decides
        $context = $this->propertyContext($parent->getSimpleChildren(), $prop);
        $context->setTranslatedParent($parent);

        // Assert supports() returns false because getAttributes returns empty
        self::assertFalse(
            $handler->supports($context),
            'supports() should return false when the property has no OneToMany attributes',
        );
    }

    /** ------------------------- Shared / Empty -------------------------.
     * @throws \ReflectionException
     */
    public function testTranslateThrowsWhenShared(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToManyBidirectionalParent();
        $prop    = new \ReflectionProperty($entity, 'simpleChildren');

        $context = $this->propertyContext($entity->getSimpleChildren(), $prop)->setShared(true);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessageMatches('/::simpleChildren is a Bidirectional OneToMany/');

        $handler->translate($context);
    }

    public function testTranslateReturnsEmptyArrayCollectionWhenEmpty(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToManyBidirectionalParent();
        $context = $this->propertyContext($entity->getSimpleChildren())->setEmpty(true);

        $result = $handler->translate($context);

        self::assertCount(0, $result);
    }

    /** ------------------------- Translate -------------------------.
     * @throws \ReflectionException
     */
    public function testTranslateClonesCollectionAndProcessesChildren(): void
    {
        $handler = $this->createHandler();

        $parent = new TranslatableOneToManyBidirectionalParent();
        // Already at the target locale: the bare test translator has no handlers, so
        // processTranslation() hands each child back as the very same instance either
        // way (see UnitTestCase::getTranslator()) -- setting the target locale up front
        // is what tells the two apart, keeping this test on the "kept" path rather than
        // the cycle-guard fallback WP22 added (see BidirectionalOneToManyHandler::translate()).
        $child1 = new TranslatableManyToOneBidirectionalChild()->setLocale('it_IT');
        $child2 = new TranslatableManyToOneBidirectionalChild()->setLocale('it_IT');

        $parent->setSimpleChildren(new ArrayCollection([$child1, $child2]));

        $metadata = new ClassMetadata(TranslatableOneToManyBidirectionalParent::class);
        $mapping  = new OneToManyAssociationMapping(
            fieldName: 'simpleChildren',
            sourceEntity: TranslatableOneToManyBidirectionalParent::class,
            targetEntity: TranslatableManyToOneBidirectionalChild::class,
        );
        $mapping->mappedBy             = 'parentSimple';
        $metadata->associationMappings = [
            'simpleChildren' => $mapping,
        ];

        $this->entityManager()->method('getClassMetadata')
            ->with(TranslatableOneToManyBidirectionalParent::class)
            ->willReturn($metadata);

        $collection = $parent->getSimpleChildren();

        $context = $this->propertyContext($collection, new \ReflectionProperty($parent, 'simpleChildren'));
        $context->setSourceLocale('en');
        $context->setTargetLocale('it_IT');
        $context->setTranslatedParent($parent);

        $result = $handler->translate($context);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(2, $result);
        foreach ($result as $child) {
            self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $child);
            self::assertSame($parent, $child->getParentSimple());
        }
    }

    /**
     * #18 negative proof: the back-reference field named by `mappedBy` is declared
     * PRIVATE one level above the child's own class. `new \ReflectionProperty(
     * $child::class, $mappedBy)` only ever looks at $child itself and throws
     * "Property ... does not exist" there -- ReflectionHelper::getProperty() walks
     * the hierarchy the same way getHierarchyProperties() already does.
     *
     * @throws \ReflectionException
     */
    public function testTranslateResolvesMappedByPropertyDeclaredOnAMappedSuperclass(): void
    {
        $handler = $this->createHandler();

        $parent = new TranslatableOneToManyBidirectionalParent();
        // Already at the target locale -- see the comment in
        // testTranslateClonesCollectionAndProcessesChildren() above.
        $child = new InheritedBackReferenceChild();
        $child->setLocale('it_IT');

        $collection = new ArrayCollection([$child]);

        $metadata = new ClassMetadata(TranslatableOneToManyBidirectionalParent::class);
        $mapping  = new OneToManyAssociationMapping(
            fieldName: 'simpleChildren',
            sourceEntity: TranslatableOneToManyBidirectionalParent::class,
            targetEntity: InheritedBackReferenceChild::class,
        );
        $mapping->mappedBy             = 'parent';
        $metadata->associationMappings = [
            'simpleChildren' => $mapping,
        ];

        $this->entityManager()->method('getClassMetadata')
            ->with(TranslatableOneToManyBidirectionalParent::class)
            ->willReturn($metadata);

        $context = $this->propertyContext($collection, new \ReflectionProperty($parent, 'simpleChildren'));
        $context->setSourceLocale('en');
        $context->setTargetLocale('it_IT');
        $context->setTranslatedParent($parent);

        $result = $handler->translate($context);

        self::assertCount(1, $result);
        $translatedChild = $result->first();
        self::assertInstanceOf(InheritedBackReferenceChild::class, $translatedChild);
        self::assertSame($parent, $translatedChild->getParent());
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateReturnsOriginalCollectionWhenPropertyHasNoMappedBy(): void
    {
        $handler = $this->createHandler();

        $parent     = new TranslatableOneToManyBidirectionalParent();
        $child      = new TranslatableManyToOneBidirectionalChild();
        $collection = new ArrayCollection([$child]);

        $parent->setSimpleChildren($collection);

        // Metadata with missing mappedBy
        $metadata = $this->createMock(ClassMetadata::class);
        $mapping  = new OneToManyAssociationMapping(
            fieldName: 'simpleChildren',
            sourceEntity: $parent::class,
            targetEntity: TranslatableManyToOneBidirectionalChild::class,
        );
        $metadata->associationMappings = [
            'simpleChildren' => $mapping,
        ];

        $this->entityManager()->method('getClassMetadata')
            ->with($parent::class)
            ->willReturn($metadata);

        $context = $this->propertyContext($collection, new \ReflectionProperty($parent, 'simpleChildren'));
        $context->setSourceLocale('en');
        $context->setTargetLocale('it_IT');
        $context->setTranslatedParent($parent);

        $result = $handler->translate($context);

        self::assertSame($collection, $result, 'Guard returned original collection when mappedBy missing.');
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateAddsNonTranslatableChildAsIs(): void
    {
        $handler = $this->createHandler();

        $parent = new TranslatableOneToManyBidirectionalParent();

        $nonTranslatableChild = new class {
            public string $name = 'foo';
        };

        /** @var ArrayCollection<int, TranslatableManyToOneBidirectionalChild> $collection */
        $collection = new ArrayCollection([$nonTranslatableChild]);
        $parent->setSimpleChildren($collection);

        // Metadata with mappedBy
        $metadata = $this->createMock(ClassMetadata::class);
        $mapping  = new OneToManyAssociationMapping(
            fieldName: 'simpleChildren',
            sourceEntity: $parent::class,
            targetEntity: TranslatableManyToOneBidirectionalChild::class,
        );
        $mapping->mappedBy             = 'parentSimple';
        $metadata->associationMappings = [
            'simpleChildren' => $mapping,
        ];

        $this->entityManager()->method('getClassMetadata')->with($parent::class)->willReturn($metadata);

        $context = $this->propertyContext($collection, new \ReflectionProperty($parent, 'simpleChildren'));
        $context->setSourceLocale('en');
        $context->setTargetLocale('it_IT');
        $context->setTranslatedParent($parent);

        $result = $handler->translate($context);

        self::assertCount(1, $result, 'Collection should have one child');
        self::assertSame($nonTranslatableChild, $result[0], 'Non-translatable child should be added as-is');
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateReturnsEmptyCollectionWhenNoParentOrProperty(): void
    {
        $handler    = $this->createHandler();
        $collection = new ArrayCollection([new TranslatableManyToOneBidirectionalChild()]);

        $context = $this->propertyContext($collection);
        $context->setSourceLocale('en');
        $context->setTargetLocale('it_IT');

        $result = $handler->translate($context);

        self::assertSame($collection, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateReusesNonTranslatableChildrenWithDebug(): void
    {
        $handler = $this->createHandler();

        $parent = new TranslatableOneToManyBidirectionalParent();

        $prop = new \ReflectionProperty($parent, 'simpleChildren');

        $nonTranslatableChild = new \stdClass(); // kein TranslatableInterface
        $children             = new ArrayCollection([$nonTranslatableChild]);

        $metadata = new ClassMetadata(TranslatableOneToManyBidirectionalParent::class);
        $mapping  = new OneToManyAssociationMapping(
            fieldName: 'simpleChildren',
            sourceEntity: TranslatableOneToManyBidirectionalParent::class,
            targetEntity: TranslatableManyToOneBidirectionalChild::class,
        );
        $mapping->mappedBy             = 'parentSimple';
        $metadata->associationMappings = [
            'simpleChildren' => $mapping,
        ];

        $this->entityManager()->method('getClassMetadata')
            ->with(TranslatableOneToManyBidirectionalParent::class)
            ->willReturn($metadata);

        // Setup context
        $context = $this->propertyContext($children, $prop);
        $context->setSourceLocale('en');
        $context->setTargetLocale('it_IT');
        $context->setTranslatedParent($parent);

        $result = $handler->translate($context);

        self::assertCount(1, $result);
        self::assertSame($nonTranslatableChild, $result->first(), 'Non-translatable child should be reused');
    }

    private function createHandler(): BidirectionalOneToManyHandler
    {
        return new BidirectionalOneToManyHandler(
            $this->attributeHelper(),
            $this->translator(),
            $this->entityManager(),
        );
    }
}
