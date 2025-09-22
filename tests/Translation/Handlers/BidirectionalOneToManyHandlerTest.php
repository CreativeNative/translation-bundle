<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\ClassMetadata;
use ErrorException;
use ReflectionException;
use ReflectionProperty;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalParent;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\BidirectionalOneToManyHandler;

final class BidirectionalOneToManyHandlerTest extends UnitTestCase
{
    private function createHandler(): BidirectionalOneToManyHandler
    {
        return new BidirectionalOneToManyHandler(
            $this->attributeHelper,
            $this->translator,
            $this->entityManager
        );
    }

    /** ------------------------- Supports -------------------------
     * @throws ReflectionException
     */

    public function testSupportsReturnsFalseWhenNotOneToMany(): void
    {
        $handler = $this->createHandler();
        $entity = new TranslatableOneToManyBidirectionalParent();
        $prop = new ReflectionProperty($entity, 'simpleChildren');

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        $this->attributeHelper->method('isOneToMany')->with($prop)->willReturn(false);

        self::assertFalse($handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsTrueWhenOneToManyWithMappedBy(): void
    {
        $handler = $this->createHandler();

        $entity = new TranslatableOneToManyBidirectionalParent();
        $prop = new ReflectionProperty($entity, 'simpleChildren');

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        $this->attributeHelper->method('isOneToMany')->with($prop)->willReturn(true);

        self::assertTrue($handler->supports($args));
    }

    /**
     * @throws ReflectionException
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
        $this->attributeHelper->method('isOneToMany')->with($prop)->willReturn(true);

        // Create TranslationArgs
        $args = new TranslationArgs($parent, 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($parent);

        // Assert supports() returns false because getAttributes returns empty
        self::assertFalse(
            $handler->supports($args),
            'supports() should return false when the property has no OneToMany attributes'
        );
    }


    /** ------------------------- Shared / Empty -------------------------
     * @throws ReflectionException
     */

    public function testHandleSharedAmongstTranslationsThrows(): void
    {
        $handler = $this->createHandler();
        $entity = new TranslatableOneToManyBidirectionalParent();
        $prop = new ReflectionProperty($entity, 'simpleChildren');

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessageMatches('/::simpleChildren is a Bidirectional OneToMany/');

        $handler->handleSharedAmongstTranslations($args);
    }

    public function testHandleEmptyOnTranslateReturnsArrayCollection(): void
    {
        $handler = $this->createHandler();
        $entity = new TranslatableOneToManyBidirectionalParent();
        $args = new TranslationArgs($entity);

        $result = $handler->handleEmptyOnTranslate($args);

        self::assertCount(0, $result);
    }

    /** ------------------------- Translate -------------------------
     * @throws ReflectionException
     */

    public function testTranslateClonesCollectionAndProcessesChildren(): void
    {
        $handler = $this->createHandler();

        $parent = new TranslatableOneToManyBidirectionalParent();
        $child1 = new TranslatableManyToOneBidirectionalChild();
        $child2 = new TranslatableManyToOneBidirectionalChild();

        $parent->setSimpleChildren(new ArrayCollection([$child1, $child2]));

        $metadata = new ClassMetadata(TranslatableOneToManyBidirectionalParent::class);
        $metadata->associationMappings = [
            'simpleChildren' => ['mappedBy' => 'parentSimple'],
        ];

        $this->entityManager->method('getClassMetadata')
            ->with(TranslatableOneToManyBidirectionalParent::class)
            ->willReturn($metadata);

        $collection = $parent->getSimpleChildren();

        $args = new TranslationArgs($collection, 'en', 'it');
        $args->setProperty(new ReflectionProperty($parent, 'simpleChildren'));
        $args->setTranslatedParent($parent);

        $result = $handler->translate($args);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(2, $result);
        foreach ($result as $child) {
            assert($child instanceof TranslatableManyToOneBidirectionalChild);
            self::assertSame($parent, $child->getParentSimple());
        }
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateReturnsOriginalCollectionWhenPropertyHasNoMappedBy(): void
    {
        $handler = $this->createHandler();

        $parent = new TranslatableOneToManyBidirectionalParent();
        $child = new TranslatableManyToOneBidirectionalChild();
        $collection = new ArrayCollection([$child]);

        $parent->setSimpleChildren($collection);

        // Metadata with missing mappedBy
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->associationMappings = [
            'simpleChildren' => [], // no mappedBy
        ];

        $this->entityManager->method('getClassMetadata')
            ->with($parent::class)
            ->willReturn($metadata);

        $args = new TranslationArgs($collection, 'en', 'it');
        $args->setProperty(new ReflectionProperty($parent, 'simpleChildren'));
        $args->setTranslatedParent($parent);

        $result = $handler->translate($args);

        self::assertSame($collection, $result, 'Guard returned original collection when mappedBy missing.');
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateAddsNonTranslatableChildAsIs(): void
    {
        $handler = $this->createHandler();

        $parent = new TranslatableOneToManyBidirectionalParent();

        $nonTranslatableChild = new class {
            public string $name = 'foo';
        };

        $collection = new ArrayCollection([$nonTranslatableChild]);
        $parent->setSimpleChildren($collection);

        // Metadata with mappedBy
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->associationMappings = [
            'simpleChildren' => ['mappedBy' => 'parentSimple'],
        ];

        $this->entityManager->method('getClassMetadata')->with($parent::class)->willReturn($metadata);

        $args = new TranslationArgs($collection, 'en', 'it');
        $args->setProperty(new ReflectionProperty($parent, 'simpleChildren'));
        $args->setTranslatedParent($parent);

        $result = $handler->translate($args);

        self::assertCount(1, $result, 'Collection should have one child');
        self::assertSame($nonTranslatableChild, $result[0], 'Non-translatable child should be added as-is');
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateReturnsEmptyCollectionWhenNoParentOrProperty(): void
    {
        $handler = $this->createHandler();
        $collection = new ArrayCollection([new TranslatableManyToOneBidirectionalChild()]);

        $args = new TranslationArgs($collection, 'en', 'it');
        $args->setProperty(null);
        $args->setTranslatedParent(null);

        $result = $handler->translate($args);

        self::assertSame($collection, $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateReusesNonTranslatableChildrenWithDebug(): void
    {
        $handler = $this->createHandler();

        $parent = new TranslatableOneToManyBidirectionalParent();

        $prop = new ReflectionProperty($parent, 'simpleChildren');

        $nonTranslatableChild = new \stdClass(); // kein TranslatableInterface
        $children = new ArrayCollection([$nonTranslatableChild]);

        $metadata = new ClassMetadata(TranslatableOneToManyBidirectionalParent::class);
        $metadata->associationMappings = [
            'simpleChildren' => ['mappedBy' => 'parentSimple'],
        ];

        $this->entityManager->method('getClassMetadata')
            ->with(TranslatableOneToManyBidirectionalParent::class)
            ->willReturn($metadata);

        // 5️⃣ Setup TranslationArgs
        $args = new TranslationArgs($children, 'en', 'it');
        $args->setProperty($prop);
        $args->setTranslatedParent($parent);

        $result = $handler->translate($args);

        self::assertCount(1, $result);
        self::assertSame($nonTranslatableChild, $result->first(), 'Non-translatable child should be reused');
    }
}
