<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToManyInverseSideMapping;
use Doctrine\ORM\Mapping\MappingException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalParent;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyOwningChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyOwningParent;
use Tmi\TranslationBundle\Fixtures\Reflection\ManyToMany\InheritedBackReferenceChild;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Handlers\BidirectionalManyToManyHandler;

#[AllowMockObjectsWithoutExpectations]
final class BidirectionalManyToManyHandlerTest extends UnitTestCase
{
    private BidirectionalManyToManyHandler $handler;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        $this->handler = new BidirectionalManyToManyHandler(
            $this->attributeHelper(),
            $this->entityManager(),
            $this->translator(),
        );
    }

    // ---------------------------------------------------
    // supports() Tests
    // ---------------------------------------------------

    public function testSupportsReturnsFalseIfNotCollectionOrMissingProperty(): void
    {
        self::assertFalse($this->handler->supports(
            $this->propertyContext('not-a-collection'),
        ));
        self::assertFalse($this->handler->supports(
            $this->propertyContext(new ArrayCollection()),
        ));
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseIfAttributeHelperSaysNotManyToMany(): void
    {
        $this->attributeHelper()->method('isManyToMany')->willReturn(false);

        $parent = new class {
            /** @var Collection<int, TranslatableManyToManyBidirectionalChild> */
            #[ManyToMany(targetEntity: TranslatableManyToManyBidirectionalChild::class, mappedBy: 'parents')]
            public Collection $children;

            public function __construct()
            {
                $this->children = new ArrayCollection();
            }
        };

        $prop    = new \ReflectionProperty($parent::class, 'children');
        $context = $this->propertyContext($parent->children, $prop);
        $context->setTranslatedParent($parent);

        self::assertFalse($this->handler->supports($context));
    }

    /**
     * supports(): attribute helper says isManyToMany, but property is a plain array with no ManyToMany attribute.
     *
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseWhenPlainArrayPropertyLacksManyToManyAttribute(): void
    {
        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        $anon = new class {
            /** @var array<int, mixed> */
            public array $plain = [];
        };
        $prop    = new \ReflectionProperty($anon::class, 'plain');
        $context = $this->propertyContext($anon->plain, $prop);
        $context->setTranslatedParent($anon);

        self::assertFalse($this->handler->supports($context));
    }

    /**
     * supports(): property has #[ManyToMany] but no mappedBy argument -> supports() must return false.
     *
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseWhenManyToManyAttributeHasNoMappedBy(): void
    {
        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        // owner class with a Collection property but NO #[ManyToMany] attribute
        $anon = new class {
            /** @var Collection<int, mixed> */
            /** @var Collection<int, mixed> */
            public Collection $items;

            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };

        $prop = new \ReflectionProperty($anon::class, 'items');

        // Pass an actual Collection as the data to be translated
        $context = $this->propertyContext($anon->items, $prop);
        $context->setTranslatedParent($anon);

        // Now supports() should execute the getAttributes(...) check and return FALSE
        self::assertFalse($this->handler->supports($context));
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseWhenNoPropertyPresent(): void
    {
        $entity = new TranslatableManyToManyBidirectionalParent();
        $entity->setLocale('en');

        $prop = new \ReflectionProperty($entity, 'title');

        $this->attributeHelper()->method('isManyToMany')->with($prop)->willReturn(false);

        $context = $this->entityContext($entity, $prop);
        $context->setTranslatedParent($entity);

        self::assertFalse($this->handler->supports($context));
    }

    /**
     * supports(): attribute helper says isManyToMany, but entity's title property has no ManyToMany attribute.
     *
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseWhenEntityPropertyLacksManyToManyAttribute(): void
    {
        $entity = new TranslatableManyToManyBidirectionalParent();
        $entity->setLocale('en_US');

        $prop = new \ReflectionProperty($entity, 'title');

        $this->attributeHelper()->method('isManyToMany')->with($prop)->willReturn(true);

        // An entity itself is never the ManyToMany property's value -- an
        // EntityTranslationContext can never satisfy the Collection check either way.
        $context = $this->entityContext($entity, $prop);
        $context->setTranslatedParent($entity);

        self::assertFalse($this->handler->supports($context));
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsTrueWhenManyToManyAttributeExists(): void
    {
        $entity = new TranslatableManyToManyBidirectionalParent();

        $entity->setLocale('en_US');

        $prop = new \ReflectionProperty($entity, 'simpleChildren');

        $this->attributeHelper()->method('isManyToMany')->with($prop)->willReturn(true);

        // The data of a ManyToMany property is the collection, not the owning entity
        $context = $this->propertyContext($entity->getSimpleChildren(), $prop);
        $context->setTargetLocale('it_IT');
        $context->setTranslatedParent($entity);

        self::assertTrue($this->handler->supports($context));
    }

    // ---------------------------------------------------
    // discoverProperty() Test
    // ---------------------------------------------------

    /**
     * discoverProperty(): when all properties are inaccessible (private/protected) and none match the collection,
     * the method must catch the ReflectionExceptions for each property and finally return null.
     *
     * We verify this by calling translate() with isEmpty() set (which uses discoverProperty()) and asserting:
     *  - the returned result is an ArrayCollection
     *  - the owner's private properties remain unchanged (we read them using Closure::bind, avoiding setAccessible)
     *
     * @throws \ReflectionException
     */
    public function testDiscoverPropertyReturnsNullWhenAllPropertiesInaccessible(): void
    {
        // create the collection that is NOT assigned to any owner property
        $collection = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);

        // owner with only private properties — ReflectionProperty::getValue will throw when called from outside
        // NOTE: no constructor parameter (avoid the ArgumentCountError)
        $owner = new readonly class {
            /** @var Collection<int, TranslatableManyToManyBidirectionalChild> */
            public Collection $a;
            /** @var Collection<int, TranslatableManyToManyBidirectionalChild> */
            public Collection $b;

            public function __construct()
            {
                // assign different collections so we can assert they were NOT replaced/cleared
                $this->a = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);
                $this->b = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);
                // note: the $collection used in the test is NOT stored on the object -> discoverProperty should NOT find it
            }
        };

        // attributeHelper should not block discovery flow (we still want to exercise discoverProperty)
        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        // Prepare context (no explicit property set) so handler will call discoverProperty()
        $context = $this->propertyContext($collection);
        $context->setTranslatedParent($owner);
        $context->setEmpty(true);

        // Read private/protected property values safely using Closure::bind (PHP 8.4 compliant)
        $read = static function (string $name) use ($owner): mixed {
            $reflection = new \ReflectionObject($owner);

            if (!$reflection->hasProperty($name)) {
                throw new \RuntimeException(sprintf('Property "%s" does not exist on class "%s".', $name, get_class($owner)));
            }

            return $reflection->getProperty($name)->getValue($owner);
        };

        $aBefore = $read('a');
        $bBefore = $read('b');

        // Sanity: they contain items
        self::assertInstanceOf(Collection::class, $aBefore);
        self::assertGreaterThan(0, $aBefore->count());
        self::assertInstanceOf(Collection::class, $bBefore);
        self::assertGreaterThan(0, $bBefore->count());

        // Call the handler: since discoverProperty will find nothing (and will catch on each private prop),
        // the isEmpty() resolution should simply return an empty ArrayCollection and not touch the owner's props.
        $result = $this->handler->translate($context);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(0, $result);

        // Read private property values after call and assert unchanged
        $aAfter = $read('a');
        $bAfter = $read('b');

        self::assertInstanceOf(Collection::class, $aAfter);
        self::assertInstanceOf(Collection::class, $bAfter);
        self::assertEquals(
            $aBefore->toArray(),
            $aAfter->toArray(),
            'Private property $a must remain unchanged when discoverProperty returns null',
        );
        self::assertEquals(
            $bBefore->toArray(),
            $bAfter->toArray(),
            'Private property $b must remain unchanged when discoverProperty returns null',
        );
    }

    public function testDiscoverPropertyCatchIsExecutedWhenAccessorThrowsOnce(): void
    {
        $collection = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);

        $owner = new class($collection) {
            /** @var Collection<int, TranslatableManyToManyBidirectionalChild> */
            public Collection $secret;

            /** @param Collection<int, TranslatableManyToManyBidirectionalChild> $visible */
            public function __construct(#[ManyToMany(targetEntity: TranslatableManyToManyBidirectionalChild::class, mappedBy: 'simpleParents')]
                public Collection $visible)
            {
                $this->secret = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);
            }

            /** @return Collection<int, TranslatableManyToManyBidirectionalChild> */
            public function getSecret(): Collection
            {
                return $this->secret;
            }
        };

        // accessor: throw for property 'secret', otherwise behave normally
        $accessor = function (\ReflectionProperty $p, object $o) {
            if ('secret' === $p->getName()) {
                throw new \RuntimeException('simulated access error');
            }

            return $p->getValue($o);
        };

        $handler = new BidirectionalManyToManyHandler(
            $this->attributeHelper(),
            $this->entityManager(),
            $this->translator(),
            $accessor,
        );

        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        $context = $this->propertyContext($collection);
        $context->setTranslatedParent($owner);
        $context->setEmpty(true);
        $result = $handler->translate($context);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(0, $result);
        self::assertCount(0, $owner->visible); // discovered & cleared
    }

    /**
     * translate() with isEmpty(): when data is not a Collection, should simply return an empty ArrayCollection.
     */
    public function testTranslateEmptyReturnsEmptyWhenDataNotCollection(): void
    {
        $context = $this->propertyContext('i am not a collection')->setEmpty(true);

        $result = $this->handler->translate($context);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(0, $result);
    }

    /**
     * Additional safety test: the isEmpty() resolution should discover property even when
     * discoverProperty encounters inaccessible properties first (covers the try/catch
     * continue branch in discoverProperty()).
     */
    public function testTranslateEmptyDiscoveryContinuesOnReflectionExceptions(): void
    {
        // similar to previous discovery test but be explicit about the collection being owned
        $collection = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);

        $owner = new class($collection) {
            /** @var Collection<int, TranslatableManyToManyBidirectionalChild> */
            public Collection $a;

            /** @param Collection<int, TranslatableManyToManyBidirectionalChild> $b */
            public function __construct(#[ManyToMany(targetEntity: TranslatableManyToManyBidirectionalChild::class, mappedBy: 'sharedParents')]
                public Collection $b)
            {
                $this->a = new ArrayCollection();
            }

            /** @return Collection<int, TranslatableManyToManyBidirectionalChild> */
            public function getA(): Collection
            {
                return $this->a;
            }
        };

        // attributeHelper should not block discovery
        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        $context = $this->propertyContext($collection);
        $context->setTranslatedParent($owner);
        $context->setEmpty(true);

        $returned = $this->handler->translate($context);

        self::assertInstanceOf(ArrayCollection::class, $returned);
        // property 'b' should have been set empty by the handler
        self::assertCount(0, $owner->b);
    }

    /**
     * @throws \ReflectionException|MappingException
     */
    public function testTranslateSharedThrowsErrorException(): void
    {
        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        $entity = new class {
            /** @var Collection<int, TranslatableManyToManyBidirectionalChild> */
            #[ManyToMany(targetEntity: TranslatableManyToManyBidirectionalChild::class, mappedBy: 'parents')]
            #[SharedAmongstTranslations]
            public Collection $sharedChildren;

            public function __construct()
            {
                $this->sharedChildren = new ArrayCollection();
            }
        };

        $prop    = new \ReflectionProperty($entity::class, 'sharedChildren');
        $context = $this->propertyContext($entity->sharedChildren, $prop);
        $context->setTranslatedParent($entity);
        $context->setShared(true);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage(
            sprintf(
                'SharedAmongstTranslations is not allowed on bidirectional ManyToMany associations. '
                .'Property "%s" of class "%s" is invalid.',
                'sharedChildren',
                $entity::class,
            ),
        );

        $this->handler->translate($context);
    }

    // ---------------------------------------------------
    // translate() Tests
    // ---------------------------------------------------

    /**
     * Normal translation path: children translated and inverse set.
     */
    public function testTranslateTranslatesAndSetsInverseMappedBy(): void
    {
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en_US');
        $child  = new TranslatableManyToManyBidirectionalChild()->setLocale('en_US');
        $parent->addSimpleChild($child);
        $child->addSimpleParent($parent);

        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        $context = $this->propertyContext($parent->getSimpleChildren());
        $context->setTranslatedParent($parent);
        $result = $this->handler->translate($context);

        self::assertCount(1, $result);

        $translatedChild = $result->first();
        self::assertInstanceOf(TranslatableManyToManyBidirectionalChild::class, $translatedChild);
        self::assertSame('en_US', $translatedChild->getLocale());
        self::assertTrue($translatedChild->getSimpleParents()->contains($parent));
    }

    /**
     * Owning-side direction: the translated entity declares inversedBy, not mappedBy.
     * The back-reference field on the related class comes from inversedBy there, and the
     * mapping is just as bidirectional -- it must not be rejected as "missing mappedBy".
     */
    public function testTranslateResolvesBackReferenceFromInversedByOnOwningSide(): void
    {
        $parent = new TranslatableManyToManyOwningParent()->setLocale('en_US');
        $child  = new TranslatableManyToManyOwningChild()->setLocale('en_US');
        $parent->addOwningChild($child);

        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        $context = $this->propertyContext($parent->getOwningChildren());
        $context->setTranslatedParent($parent);
        $result = $this->handler->translate($context);

        self::assertCount(1, $result);

        $translatedChild = $result->first();
        self::assertInstanceOf(TranslatableManyToManyOwningChild::class, $translatedChild);
        self::assertTrue($translatedChild->getOwningParents()->contains($parent));
    }

    /**
     * No ManyToMany attribute on the property (XML/YAML mapping): the back-reference field
     * comes from the association metadata instead.
     *
     * @throws \ReflectionException|MappingException
     */
    public function testTranslateResolvesMappedByFromMetadataWhenAttributeIsAbsent(): void
    {
        // Property without a ManyToMany attribute, so resolution has to fall back to metadata
        $unmapped = new class {
            /** @var Collection<int, mixed> */
            public Collection $items;

            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };

        $parent     = new TranslatableManyToManyOwningParent()->setLocale('en_US');
        $child      = new TranslatableManyToManyOwningChild()->setLocale('en_US');
        $collection = new ArrayCollection([$child]);
        $prop       = new \ReflectionProperty($unmapped::class, 'items');

        $mapping = new ManyToManyInverseSideMapping(
            'items',
            $parent::class,
            TranslatableManyToManyOwningChild::class,
        );
        $mapping->mappedBy = 'owningParents';

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMapping')->with('items')->willReturn($mapping);
        $this->entityManager()->method('getClassMetadata')->willReturn($meta);

        $context = $this->propertyContext($collection, $prop);
        $context->setTranslatedParent($parent);

        $result = $this->handler->translate($context);

        self::assertCount(1, $result);

        $translatedChild = $result->first();
        self::assertInstanceOf(TranslatableManyToManyOwningChild::class, $translatedChild);
        self::assertTrue($translatedChild->getOwningParents()->contains($parent));
    }

    /**
     * #18 negative proof: the back-reference collection named by `mappedBy` is
     * declared PRIVATE one level above the item's own class. `new
     * \ReflectionProperty($item::class, $mappedBy)` only ever looks at $item
     * itself and throws "Property ... does not exist" there --
     * ReflectionHelper::getProperty() walks the hierarchy the same way
     * getHierarchyProperties() already does. Exercises both call sites at once:
     * the detach/restore in translate() and addBackReference().
     *
     * @throws \ReflectionException|MappingException
     */
    public function testTranslateResolvesMappedByPropertyDeclaredOnAMappedSuperclass(): void
    {
        $unmapped = new class {
            /** @var Collection<int, mixed> */
            public Collection $items;

            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };

        $parent = new TranslatableManyToManyOwningParent()->setLocale('en_US');
        $item   = new InheritedBackReferenceChild();
        $item->setLocale('en_US');
        $collection = new ArrayCollection([$item]);
        $prop       = new \ReflectionProperty($unmapped::class, 'items');

        $mapping           = new ManyToManyInverseSideMapping('items', $parent::class, InheritedBackReferenceChild::class);
        $mapping->mappedBy = 'parents';

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMapping')->with('items')->willReturn($mapping);
        $this->entityManager()->method('getClassMetadata')->willReturn($meta);

        $context = $this->propertyContext($collection, $prop);
        $context->setTranslatedParent($parent);

        $result = $this->handler->translate($context);

        self::assertCount(1, $result);

        $translatedChild = $result->first();
        self::assertInstanceOf(InheritedBackReferenceChild::class, $translatedChild);
        self::assertTrue($translatedChild->getParents()->contains($parent));
    }

    /**
     * The back-reference on the related class may not be initialised yet. Setting the owner
     * then means creating the collection rather than adding to it.
     *
     * @throws \ReflectionException|MappingException
     */
    public function testTranslateInitialisesAnUnsetBackReference(): void
    {
        $item = new class implements TranslatableInterface {
            use TranslatableTrait;

            /** @var Collection<int, mixed>|null */
            public Collection|null $owners = null;
        };
        $item->setLocale('en_US');

        // Property without a ManyToMany attribute, so the back-reference name comes from the
        // mocked metadata below and can point at $item::$owners.
        $unmapped = new class {
            /** @var Collection<int, mixed> */
            public Collection $items;

            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };

        $parent = new TranslatableManyToManyOwningParent()->setLocale('en_US');
        $prop   = new \ReflectionProperty($unmapped::class, 'items');

        $mapping           = new ManyToManyInverseSideMapping('items', $parent::class, $item::class);
        $mapping->mappedBy = 'owners';

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMapping')->willReturn($mapping);
        $this->entityManager()->method('getClassMetadata')->willReturn($meta);

        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        // The bare test translator has no handlers, so translate() hands the item back
        // unchanged and its (restored) back-reference is still null.
        $context = $this->propertyContext(new ArrayCollection([$item]), $prop);
        $context->setTranslatedParent($parent);

        $result = $this->handler->translate($context);

        self::assertCount(1, $result);
        self::assertInstanceOf(Collection::class, $item->owners);
        self::assertTrue($item->owners->contains($parent));
    }

    /**
     * Not a collection -> exception.
     */
    public function testTranslateThrowsIfNotCollection(): void
    {
        $context = $this->propertyContext('not-a-collection');

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('BidirectionalManyToManyHandler::translate() expects a Collection.');
        $this->handler->translate($context);
    }

    /**
     * Metadata present but mappedBy null -> throws.
     *
     * @throws \ReflectionException|MappingException
     */
    public function testTranslateThrowsIfMappedByNull(): void
    {
        $parent = new class {
            /** @var Collection<int, mixed> */
            public Collection $items;

            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };
        $collection = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);
        $prop       = new \ReflectionProperty($parent::class, 'items');

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn(['items' => ['mappedBy' => null]]);
        $this->entityManager()->method('getClassMetadata')->willReturn($meta);

        $context = $this->propertyContext($collection, $prop);
        $context->setTranslatedParent($parent);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('is not a bidirectional ManyToMany');
        $this->handler->translate($context);
    }

    /**
     * translate(): when no translatedParent or property is provided, the handler must return a copy of the collection
     * (ArrayCollection containing the same items).
     */
    public function testTranslateReturnsCopyWhenOwnerOrPropertyMissing(): void
    {
        $child      = new TranslatableManyToManyBidirectionalChild()->setLocale('en_US');
        $collection = new ArrayCollection([$child]);

        // do NOT set translatedParent nor property
        $context = $this->propertyContext($collection);

        $result = $this->handler->translate($context);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(1, $result);

        // the handler returns a new ArrayCollection built from ->toArray(); items are the same instances
        self::assertSame($child, $result->first());
    }

    /**
     * Metadata missing -> throws mapping-not-found.
     *
     * @throws \ReflectionException|MappingException
     */
    public function testTranslateThrowsIfAssociationMissingOrNoMappedBy(): void
    {
        $parent = new class {
            /** @var Collection<int, mixed> */
            public Collection $items;

            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };
        $prop = new \ReflectionProperty($parent::class, 'items');

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn([]);
        $this->entityManager()->method('getClassMetadata')->willReturn($meta);

        $context = $this->propertyContext($parent->items, $prop);
        $context->setTranslatedParent($parent);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('is not a bidirectional ManyToMany');
        $this->handler->translate($context);
    }

    // ---------------------------------------------------
    // isShared() Tests
    // ---------------------------------------------------

    /**
     * Normal shared translation: items processed, inverse set.
     */
    public function testTranslateSharedProcessesItemsAndSetsInverse(): void
    {
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en_US');
        $child  = new TranslatableManyToManyBidirectionalChild()->setLocale('en_US');
        $parent->addSharedChild($child);
        $child->addSharedParents($parent);

        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        $context = $this->propertyContext($parent->getSharedChildren());
        $context->setTranslatedParent($parent);
        $context->setShared(true);
        $result = $this->handler->translate($context);

        self::assertCount(1, $result);

        $translatedChild = $result->first();
        self::assertInstanceOf(TranslatableManyToManyBidirectionalChild::class, $translatedChild);
        self::assertSame('en_US', $translatedChild->getLocale());
        self::assertTrue($translatedChild->getSharedParents()->contains($parent));
    }

    /**
     * Not a collection -> exception.
     */
    public function testTranslateSharedThrowsIfNotCollection(): void
    {
        $context = $this->propertyContext('not-a-collection')->setShared(true);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('BidirectionalManyToManyHandler::translate() expects a Collection.');
        $this->handler->translate($context);
    }

    /**
     * No owner or property -> returns empty.
     */
    public function testTranslateSharedReturnsEmptyIfNoOwnerOrProperty(): void
    {
        $context = $this->propertyContext(new ArrayCollection())->setShared(true);
        $result  = $this->handler->translate($context);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(0, $result);
    }

    /**
     * Association exists in metadata but mappedBy missing -> throws has-no-mappedBy.
     *
     * @throws \ReflectionException|MappingException
     */
    public function testTranslateSharedThrowsWhenMappedByMissing(): void
    {
        $parent = new class {
            /** @var Collection<int, mixed> */
            public Collection $items;

            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };
        $prop = new \ReflectionProperty($parent::class, 'items');

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn(['items' => ['fieldName' => 'items']]);
        $this->entityManager()->method('getClassMetadata')->willReturn($meta);

        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        $context = $this->propertyContext($parent->items, $prop);
        $context->setTranslatedParent($parent);
        $context->setShared(true);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('is not a bidirectional ManyToMany');
        $this->handler->translate($context);
    }

    /**
     * Covers non-TranslatableInterface items being passed through
     * to the new collection unchanged (not translated).
     *
     * @throws \ReflectionException|MappingException
     */
    public function testTranslatePassesThroughNonTranslatableItem(): void
    {
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en_US');

        $prop = new \ReflectionProperty($parent::class, 'simpleChildren');

        // stdClass is not TranslatableInterface -> passed through as-is
        $nonTranslatable       = new \stdClass();
        $nonTranslatable->name = 'not-translatable';

        $collection = new ArrayCollection([$nonTranslatable]);

        $this->attributeHelper()->method('isManyToMany')->willReturn(true);

        $context = $this->propertyContext($collection, $prop);
        $context->setTranslatedParent($parent);
        $result = $this->handler->translate($context);

        // The non-translatable item should be passed through (added to collection, not translated)
        self::assertCount(1, $result);
        self::assertSame($nonTranslatable, $result->first());
    }

    // ---------------------------------------------------
    // isEmpty() Tests
    // ---------------------------------------------------

    /**
     * Clears owner's collection if discoverable.
     */
    public function testTranslateEmptyClearsOwnerCollection(): void
    {
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en_US');
        $child  = new TranslatableManyToManyBidirectionalChild()->setLocale('en_US');
        $parent->addSimpleChild($child);
        $child->addSimpleParent($parent);

        $context = $this->propertyContext($parent->getSimpleChildren());
        $context->setTranslatedParent($parent);
        $context->setEmpty(true);
        $result = $this->handler->translate($context);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertEmpty($parent->getSimpleChildren());
    }

    public function testTranslateEmptySwallowsException(): void
    {
        $collection = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);

        $owner = new class {
            /** @var Collection<int, TranslatableManyToManyBidirectionalChild> */
            #[ManyToMany(targetEntity: TranslatableManyToManyBidirectionalChild::class, mappedBy: 'dummy')]
            public Collection $trouble;

            public function __construct()
            {
                $this->trouble = new ArrayCollection();
            }
        };

        $mockProp = $this->getMockBuilder(\ReflectionProperty::class)
            ->setConstructorArgs([$owner::class, 'trouble'])
            ->onlyMethods(['setValue'])
            ->getMock();

        $mockProp->method('setValue')
            ->willThrowException(new \RuntimeException('simulated setValue failure'));

        $context = $this->propertyContext($collection, $mockProp);
        $context->setTranslatedParent($owner);
        $context->setEmpty(true);

        $result = $this->handler->translate($context);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(0, $result);

        self::assertCount(0, $owner->trouble);
    }
}
