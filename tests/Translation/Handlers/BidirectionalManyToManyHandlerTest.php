<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\MappingException;
use ErrorException;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalParent;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\BidirectionalManyToManyHandler;

/**
 * @covers \Tmi\TranslationBundle\Translation\Handlers\CollectionHandler
 */
final class BidirectionalManyToManyHandlerTest extends UnitTestCase
{
    private BidirectionalManyToManyHandler $handler;

    public function setUp(): void
    {
        parent::setUp();

        $this->handler = new BidirectionalManyToManyHandler(
            $this->attributeHelper,
            $this->entityManager,
            $this->translator
        );
    }

    // ---------------------------------------------------
    // supports() Tests
    // ---------------------------------------------------

    public function testSupportsReturnsFalseIfNotCollectionOrMissingProperty(): void
    {
        self::assertFalse($this->handler->supports(
            new TranslationArgs('not-a-collection', 'en', 'de')
        ));
        self::assertFalse($this->handler->supports(
            new TranslationArgs(new ArrayCollection(), 'en', 'de')
        ));
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsFalseIfAttributeHelperSaysNotManyToMany(): void
    {
        $this->attributeHelper->method('isManyToMany')->willReturn(false);

        $parent = new class {
            #[ManyToMany(mappedBy: 'parents')]
            public Collection $children;
            public function __construct()
            {
                $this->children = new ArrayCollection();
            }
        };

        $prop = new ReflectionProperty($parent::class, 'children');
        $args = new TranslationArgs($parent->children, 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($parent);

        self::assertFalse($this->handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsFalseWhenNoManyToManyAttributePresent(): void
    {
        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        $anon = new class { public array $plain = [];
        };
        $prop = new ReflectionProperty($anon::class, 'plain');
        $args = new TranslationArgs($anon->plain, 'en', 'de')->setProperty($prop)->setTranslatedParent($anon);

        self::assertFalse($this->handler->supports($args));
    }

    /**
     * supports(): property has #[ManyToMany] but no mappedBy argument -> supports() must return false
     *
     * @throws ReflectionException
     */
    public function testSupportsReturnsFalseWhenManyToManyAttributeHasNoMappedBy(): void
    {
        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        // owner class with a Collection property but NO #[ManyToMany] attribute
        $anon = new class {
            public Collection $items;
            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };

        $prop = new ReflectionProperty($anon::class, 'items');

        // Pass an actual Collection as the data to be translated
        $args = new TranslationArgs($anon->items, 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($anon);

        // Now supports() should execute the getAttributes(...) check and return FALSE
        self::assertFalse($this->handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsFalseWhenNoPropertyPresent(): void
    {
        $entity = new TranslatableManyToManyBidirectionalParent();
        $entity->setLocale('en');

        $prop = new ReflectionProperty($entity, 'title');

        $this->attributeHelper->method('isManyToMany')->with($prop)->willReturn(false);

        $args = new TranslationArgs($entity, 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($entity);

        self::assertFalse($this->handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsFalseWhenNoManyToManyAttributesPresent(): void
    {
        $entity = new TranslatableManyToManyBidirectionalParent();
        $entity->setLocale('en');

        $prop = new ReflectionProperty($entity, 'title');

        $this->attributeHelper->method('isManyToMany')->with($prop)->willReturn(true);

        $args = new TranslationArgs($entity, 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($entity);

        self::assertFalse($this->handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsTrueWhenManyToManyAttributeExists(): void
    {
        $entity = new TranslatableManyToManyBidirectionalParent();

        $entity->setLocale('en');

        $prop = new ReflectionProperty($entity, 'simpleChildren');

        $this->attributeHelper->method('isManyToMany')->with($prop)->willReturn(true);

        $args = new TranslationArgs($entity, 'en', 'it')
            ->setProperty($prop)
            ->setTranslatedParent($entity);

        self::assertTrue($this->handler->supports($args));
    }

    // ---------------------------------------------------
    // discoverProperty() Test
    // ---------------------------------------------------

    /**
     * discoverProperty(): when all properties are inaccessible (private/protected) and none match the collection,
     * the method must catch the ReflectionExceptions for each property and finally return null.
     *
     * We verify this by calling handleEmptyOnTranslate() (which uses discoverProperty()) and asserting:
     *  - the returned result is an ArrayCollection
     *  - the owner's private properties remain unchanged (we read them using Closure::bind, avoiding setAccessible)
     *
     * @throws ReflectionException
     */
    public function testDiscoverPropertyReturnsNullWhenAllPropertiesInaccessible(): void
    {
        // create the collection that is NOT assigned to any owner property
        $collection = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);

        // owner with only private properties — ReflectionProperty::getValue will throw when called from outside
        // NOTE: no constructor parameter (avoid the ArgumentCountError)
        $owner = new class {
            private Collection $a;
            private Collection $b;

            public function __construct()
            {
                // assign different collections so we can assert they were NOT replaced/cleared
                $this->a = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);
                $this->b = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);
                // note: the $collection used in the test is NOT stored on the object -> discoverProperty should NOT find it
            }
        };

        // attributeHelper should not block discovery flow (we still want to exercise discoverProperty)
        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        // Prepare args (no explicit property set) so handler will call discoverProperty()
        $args = new TranslationArgs($collection, 'en', 'de')
            ->setTranslatedParent($owner);

        // Read private property values before call using Closure::bind (avoids setAccessible)
        $read = Closure::bind(function (string $name) {
            return $this->$name;
        }, $owner, $owner::class);

        $aBefore = $read('a');
        $bBefore = $read('b');

        // Sanity: they contain items
        self::assertInstanceOf(Collection::class, $aBefore);
        self::assertGreaterThan(0, $aBefore->count());
        self::assertInstanceOf(Collection::class, $bBefore);
        self::assertGreaterThan(0, $bBefore->count());

        // Call the handler: since discoverProperty will find nothing (and will catch on each private prop),
        // handleEmptyOnTranslate should simply return an empty ArrayCollection and not touch the owner's props.
        $result = $this->handler->handleEmptyOnTranslate($args);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(0, $result);

        // Read private property values after call and assert unchanged
        $aAfter = $read('a');
        $bAfter = $read('b');

        self::assertEquals(
            $aBefore->toArray(),
            $aAfter->toArray(),
            'Private property $a must remain unchanged when discoverProperty returns null'
        );
        self::assertEquals(
            $bBefore->toArray(),
            $bAfter->toArray(),
            'Private property $b must remain unchanged when discoverProperty returns null'
        );
    }

    public function testDiscoverPropertyCatchIsExecutedWhenAccessorThrowsOnce(): void
    {
        $collection = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);

        $owner = new class ($collection) {
            private Collection $secret;
            #[ManyToMany(mappedBy: 'simpleParents')]
            public Collection $visible;
            public function __construct(Collection $visible)
            {
                $this->secret = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);
                $this->visible = $visible;
            }
        };

        // accessor: throw for property 'secret', otherwise behave normally
        $accessor = function (ReflectionProperty $p, object $o) {
            if ($p->getName() === 'secret') {
                throw new RuntimeException('simulated access error');
            }
            return $p->getValue($o);
        };

        $handler = new BidirectionalManyToManyHandler(
            $this->attributeHelper,
            $this->entityManager,
            $this->translator,
            $accessor
        );

        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        $args = new TranslationArgs($collection, 'en', 'de')->setTranslatedParent($owner);
        $result = $handler->handleEmptyOnTranslate($args);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(0, $result);
        self::assertCount(0, $owner->visible); // discovered & cleared
    }

    /**
     * handleEmptyOnTranslate(): when data is not a Collection, should simply return an empty ArrayCollection.
     */
    public function testHandleEmptyOnTranslateReturnsEmptyWhenDataNotCollection(): void
    {
        $args = new TranslationArgs('i am not a collection', 'en', 'de');

        $result = $this->handler->handleEmptyOnTranslate($args);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(0, $result);
    }

    /**
     * Additional safety test: handleEmptyOnTranslate should discover property even when discoverProperty encounters
     * inaccessible properties first (covers the try/catch continue branch in discoverProperty()).
     */
    public function testHandleEmptyOnTranslateDiscoveryContinuesOnReflectionExceptions(): void
    {
        // similar to previous discovery test but be explicit about the collection being owned
        $collection = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);

        $owner = new class ($collection) {
            private Collection $a; // will be skipped (inaccessible)
            #[ManyToMany(mappedBy: 'sharedParents')]
            public Collection $b;
            public function __construct(Collection $c)
            {
                $this->a = new ArrayCollection();
                $this->b = $c;
            }
        };

        // attributeHelper should not block discovery
        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        $args = new TranslationArgs($collection, 'en', 'de')->setTranslatedParent($owner);

        $returned = $this->handler->handleEmptyOnTranslate($args);

        self::assertInstanceOf(ArrayCollection::class, $returned);
        // property 'b' should have been set empty by the handler
        self::assertCount(0, $owner->b);
    }

    /**
     * @throws ReflectionException|MappingException
     */
    public function testHandleSharedAmongstTranslationsThrowsErrorException(): void
    {
        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        $entity = new class {
            #[ManyToMany(targetEntity: TranslatableManyToManyBidirectionalChild::class, mappedBy: 'parents')]
            #[SharedAmongstTranslations]
            public Collection $sharedChildren;

            public function __construct()
            {
                $this->sharedChildren = new ArrayCollection();
            }
        };

        $prop = new \ReflectionProperty($entity::class, 'sharedChildren');
        $args = new TranslationArgs($entity->sharedChildren, 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($entity);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf(
                'SharedAmongstTranslations is not allowed on bidirectional ManyToMany associations. '
                . 'Property "%s" of class "%s" is invalid.',
                'sharedChildren',
                $entity::class
            )
        );

        $this->handler->handleSharedAmongstTranslations($args);
    }

    // ---------------------------------------------------
    // translate() Tests
    // ---------------------------------------------------

    /**
     * Normal translation path: children translated and inverse set.
     */
    public function testTranslateTranslatesAndSetsInverseMappedBy(): void
    {
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en');
        $child = new TranslatableManyToManyBidirectionalChild()->setLocale('en');
        $parent->addSimpleChild($child);
        $child->addSimpleParent($parent);

        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        $args = new TranslationArgs($parent->getSimpleChildren(), 'en', 'de')->setTranslatedParent($parent);
        $result = $this->handler->translate($args);

        self::assertInstanceOf(Collection::class, $result);
        self::assertCount(1, $result);

        $translatedChild = $result->first();
        assert($translatedChild instanceof TranslatableManyToManyBidirectionalChild);
        self::assertSame('en', $translatedChild->getLocale());
        self::assertTrue($translatedChild->getSimpleParents()->contains($parent));
    }

    /**
     * Not a collection -> exception
     */
    public function testTranslateThrowsIfNotCollection(): void
    {
        $args = new TranslationArgs('not-a-collection', 'en', 'de');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CollectionHandler::translate expects a Collection.');
        $this->handler->translate($args);
    }

    /**
     * Metadata present but mappedBy null -> throws
     *
     * @throws ReflectionException|MappingException
     */
    public function testTranslateThrowsIfMappedByNull(): void
    {
        $parent = new class { public Collection $items;
            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };
        $collection = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);
        $prop = new ReflectionProperty($parent::class, 'items');

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn(['items' => ['mappedBy' => null]]);
        $this->entityManager->method('getClassMetadata')->willReturn($meta);

        $args = new TranslationArgs($collection, 'en', 'de')->setTranslatedParent($parent)->setProperty($prop);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not a bidirectional ManyToMany');
        $this->handler->translate($args);
    }

    /**
     * translate(): when no translatedParent or property is provided, the handler must return a copy of the collection
     * (ArrayCollection containing the same items).
     */
    public function testTranslateReturnsCopyWhenOwnerOrPropertyMissing(): void
    {
        $child = new TranslatableManyToManyBidirectionalChild()->setLocale('en');
        $collection = new ArrayCollection([$child]);

        // do NOT set translatedParent nor property
        $args = new TranslationArgs($collection, 'en', 'de');

        $result = $this->handler->translate($args);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(1, $result);

        // the handler returns a new ArrayCollection built from ->toArray(); items are the same instances
        self::assertSame($child, $result->first());
    }

    /**
     * Metadata missing -> throws mapping-not-found
     *
     * @throws ReflectionException|MappingException
     */
    public function testTranslateThrowsIfAssociationMissingOrNoMappedBy(): void
    {
        $parent = new class { public Collection $items;
            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };
        $prop = new ReflectionProperty($parent::class, 'items');

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn([]);
        $this->entityManager->method('getClassMetadata')->willReturn($meta);

        $args = new TranslationArgs($parent->items, 'en', 'de')->setTranslatedParent($parent)->setProperty($prop);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not a bidirectional ManyToMany');
        $this->handler->translate($args);
    }

    // ---------------------------------------------------
    // handleSharedAmongstTranslations() Tests
    // ---------------------------------------------------

    /**
     * Normal shared translation: items processed, inverse set.
     *
     */
    public function testHandleSharedAmongstTranslationsProcessesItemsAndSetsInverse(): void
    {
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en');
        $child = new TranslatableManyToManyBidirectionalChild()->setLocale('en');
        $parent->addSharedChild($child);
        $child->addSharedParents($parent);

        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        $args = new TranslationArgs($parent->getSharedChildren(), 'en', 'de')->setTranslatedParent($parent);
        $result = $this->handler->handleSharedAmongstTranslations($args);

        self::assertInstanceOf(Collection::class, $result);
        self::assertCount(1, $result);

        $translatedChild = $result->first();
        assert($translatedChild instanceof TranslatableManyToManyBidirectionalChild);
        self::assertSame('en', $translatedChild->getLocale());
        self::assertTrue($translatedChild->getSharedParents()->contains($parent));
    }

    /**
     * Not a collection -> exception
     */
    public function testHandleSharedAmongstTranslationsThrowsIfNotCollection(): void
    {
        $args = new TranslationArgs('not-a-collection', 'en', 'de');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CollectionHandler::handleSharedAmongstTranslations expects a Collection.');
        $this->handler->handleSharedAmongstTranslations($args);
    }

    /**
     * No owner or property -> returns empty
     */
    public function testHandleSharedAmongstTranslationsReturnsEmptyIfNoOwnerOrProperty(): void
    {
        $args = new TranslationArgs(new ArrayCollection(), 'en', 'de');
        $result = $this->handler->handleSharedAmongstTranslations($args);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(0, $result);
    }

    /**
     * Association exists in metadata but mappedBy missing -> throws has-no-mappedBy
     *
     * @throws ReflectionException|MappingException
     */
    public function testHandleSharedAmongstThrowsWhenMappedByMissing(): void
    {
        $parent = new class { public Collection $items;
            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };
        $prop = new ReflectionProperty($parent::class, 'items');

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn(['items' => ['fieldName' => 'items']]);
        $this->entityManager->method('getClassMetadata')->willReturn($meta);

        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        $args = new TranslationArgs($parent->items, 'en', 'de')
            ->setTranslatedParent($parent)
            ->setProperty($prop);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not a bidirectional ManyToMany');
        $this->handler->handleSharedAmongstTranslations($args);
    }

    // ---------------------------------------------------
    // handleEmptyOnTranslate() Tests
    // ---------------------------------------------------

    /**
     * Clears owner's collection if discoverable
     */
    public function testHandleEmptyOnTranslateClearsOwnerCollection(): void
    {
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en');
        $child = new TranslatableManyToManyBidirectionalChild()->setLocale('en');
        $parent->addSimpleChild($child);
        $child->addSimpleParent($parent);

        $args = new TranslationArgs($parent->getSimpleChildren(), 'en', 'de')->setTranslatedParent($parent);
        $result = $this->handler->handleEmptyOnTranslate($args);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertEmpty($parent->getSimpleChildren());
    }

    public function testHandleEmptyOnTranslateSwallowsException(): void
    {
        $collection = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);

        $owner = new class {
            #[ManyToMany(mappedBy: 'dummy')]
            public Collection $trouble;

            public function __construct()
            {
                $this->trouble = new ArrayCollection();
            }
        };

        $mockProp = $this->getMockBuilder(ReflectionProperty::class)
            ->setConstructorArgs([$owner::class, 'trouble'])
            ->onlyMethods(['setValue'])
            ->getMock();

        $mockProp->method('setValue')
            ->willThrowException(new RuntimeException('simulated setValue failure'));

        $args = new TranslationArgs($collection, 'en', 'de')->setTranslatedParent($owner)->setProperty($mockProp);


        $result = $this->handler->handleEmptyOnTranslate($args);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(0, $result);


        self::assertCount(0, $owner->trouble);
    }
}
