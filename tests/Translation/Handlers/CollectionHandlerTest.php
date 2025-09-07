<?php

declare(strict_types=1);

namespace TMI\TranslationBundle\Test\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToMany;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalChild;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalParent;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslatorInterface;
use TMI\TranslationBundle\Translation\Handlers\CollectionHandler;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * @covers \TMI\TranslationBundle\Translation\Handlers\CollectionHandler
 */
final class CollectionHandlerTest extends TestCase
{
    private AttributeHelper $attributeHelper;
    private MockObject $em;
    private EntityTranslatorInterface $translator;
    private CollectionHandler $handler;

    public function setUp(): void
    {
        parent::setUp();

        $this->attributeHelper = $this->createMock(AttributeHelper::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        // translator stub implements the interface and exposes processTranslation() and translate()

        $this->translator = new class implements EntityTranslatorInterface {

            public int $processCalls = 0;
            public int $translateCalls = 0;

            public function translate(TranslatableInterface $entity, string $locale): TranslatableInterface
            {
                $clone = clone $entity;
                $clone->setLocale($locale);
                return $clone;
            }

            public function processTranslation(TranslationArgs $args): mixed
            {
                $this->processCalls++;
                $data = $args->getDataToBeTranslated();
                if (is_string($data)) {
                    return strtoupper($data);
                }
                $clone = clone $data;
                if (method_exists($clone, 'setLocale')) {
                    $clone->setLocale($args->getTargetLocale());
                }
                return $clone;
            }

            public function afterLoad(TranslatableInterface $entity): void
            {
            }

            public function beforePersist(TranslatableInterface $entity, EntityManagerInterface $em): void
            {
            }

            public function beforeUpdate(TranslatableInterface $entity, EntityManagerInterface $em): void
            {
            }

            public function beforeRemove(TranslatableInterface $entity, EntityManagerInterface $em): void
            {
            }
        };

        $this->handler = new CollectionHandler(
            $this->attributeHelper,
            $this->em,
            $this->translator
        );
    }

    public function testSupportsReturnsFalseIfNotCollectionOrMissingProperty(): void
    {
        $handler = new CollectionHandler($this->attributeHelper, $this->em, $this->translator);

        // data not a collection
        $args = new TranslationArgs('not-a-collection', 'en', 'de');
        self::assertFalse($handler->supports($args));

        // collection but no property
        $args = new TranslationArgs(new ArrayCollection(), 'en', 'de');
        self::assertFalse($handler->supports($args));
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
        $args = new TranslationArgs($parent->children ?? new ArrayCollection(), 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($parent);

        // isManyToMany returns false -> supports false
        $handler = new CollectionHandler($this->attributeHelper, $this->em, $this->translator);
        self::assertFalse($handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsFalseWhenNoManyToManyAttributePresent(): void
    {
        // attributeHelper says true, but property has no ManyToMany attribute
        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        $anon = new class {
            public array $plain = [];
        };
        $prop = new ReflectionProperty($anon::class, 'plain');

        $handler = new CollectionHandler($this->attributeHelper, $this->em, $this->translator);

        $args = new TranslationArgs($anon->plain, 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($anon);

        self::assertFalse($handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testHandleSharedAmongstTranslationsProcessesItemsAndSetsInverse(): void
    {
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en');
        $child = new TranslatableManyToManyBidirectionalChild()->setLocale('en');

        $parent->addSharedChild($child);
        $child->addSharedParents($parent);

        $args = new TranslationArgs($parent->getSharedChildren(), 'en', 'de');
        $args->setTranslatedParent($parent);

        $result = $this->handler->handleSharedAmongstTranslations($args);

        self::assertInstanceOf(Collection::class, $result);
        self::assertCount(1, $result);

        $translatedChild = $result->first();
        self::assertSame('de', $translatedChild->getLocale());
        self::assertTrue($translatedChild->parents->contains($parent));
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateTranslatesAndSetsInverseMappedBy(): void
    {
        $parent = new TranslatableManyToManyBidirectionalParent()->setLocale('en');
        $child = new TranslatableManyToManyBidirectionalChild()->setLocale('en');

        $parent->addSimpleChild($child);
        $child->addSimpleParent($parent);

        $args = new TranslationArgs($parent->getSimpleChildren(), 'en', 'de');
        $args->setTranslatedParent($parent);

        $handler = new CollectionHandler($this->attributeHelper, $this->em, $this->translator);
        $result = $handler->translate($args);

        self::assertInstanceOf(Collection::class, $result);
        self::assertCount(1, $result);

        $translatedChild = $result->first();
        self::assertSame('de', $translatedChild->getLocale());
        self::assertTrue($translatedChild->parents->contains($parent));
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateThrowsIfAssociationMissingOrNoMappedBy(): void
    {
        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        $parent = new class {
            public Collection $items;

            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };
        $prop = new ReflectionProperty($parent::class, 'items');

        $meta = $this->createMock(ClassMetadata::class);
        // no mapping -> exception
        $meta->method('getAssociationMappings')->willReturn([]);
        $this->em->method('getClassMetadata')->willReturn($meta);

        $handler = new CollectionHandler($this->attributeHelper, $this->em, $this->translator);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Association mapping not found');
        $handler->translate(
            new TranslationArgs($parent->items, 'en', 'de')
                ->setProperty($prop)
                ->setTranslatedParent($parent)
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testHandleSharedAmongstThrowsWhenMappedByMissing(): void
    {
        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        $parent = new class {
            public Collection $items;

            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };
        $prop = new ReflectionProperty($parent::class, 'items');

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn([
            'items' => ['fieldName' => 'items'], // mappedBy missing
        ]);
        $this->em->method('getClassMetadata')->willReturn($meta);

        $handler = new CollectionHandler($this->attributeHelper, $this->em, $this->translator);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has no "mappedBy"');
        $handler->handleSharedAmongstTranslations(
            new TranslationArgs($parent->items, 'en', 'de')
                ->setProperty($prop)
                ->setTranslatedParent($parent)
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsFalseIfNoManyToManyAttribute(): void
    {
        $parent = new class {
            public Collection $items;
            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };

        $prop = new ReflectionProperty($parent::class, 'items');

        $this->attributeHelper
            ->method('isManyToMany')
            ->willReturn(true);

        // No #[ManyToMany] attribute on the property
        $args = new TranslationArgs(new ArrayCollection(), 'en', 'de')
            ->setProperty($prop);

        self::assertFalse($this->handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testHandleSharedAmongstTranslationsThrowsIfNotCollection(): void
    {
        $args = new TranslationArgs('not-a-collection', 'en', 'de');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CollectionHandler::handleSharedAmongstTranslations expects a Collection.');

        $this->handler->handleSharedAmongstTranslations($args);
    }

    /**
     * @throws ReflectionException
     */
    public function testHandleSharedAmongstTranslationsReturnsEmptyIfNoOwnerOrProperty(): void
    {
        $args = new TranslationArgs(new ArrayCollection(), 'en', 'de');
        // no setTranslatedParent, no setProperty

        $result = $this->handler->handleSharedAmongstTranslations($args);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(0, $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testHandleSharedAmongstTranslationsThrowsIfAssociationMissing(): void
    {
        $parent = new TranslatableManyToManyBidirectionalParent();
        $collection = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);

        $prop = new ReflectionProperty(TranslatableManyToManyBidirectionalParent::class, 'sharedChildren');

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn([]); // no mapping
        $this->em->method('getClassMetadata')->willReturn($meta);

        $args = new TranslationArgs($collection, 'en', 'de')
            ->setTranslatedParent($parent)
            ->setProperty($prop);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Association mapping not found');

        $this->handler->handleSharedAmongstTranslations($args);
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateThrowsIfNotCollection(): void
    {
        $args = new TranslationArgs('not-a-collection', 'en', 'de');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CollectionHandler::translate expects a Collection.');

        $this->handler->translate($args);
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateThrowsIfMappedByNull(): void
    {
        $parent = new class {
            public Collection $items;
            public function __construct()
            {
                $this->items = new ArrayCollection();
            }
        };

        $collection = new ArrayCollection([new TranslatableManyToManyBidirectionalChild()]);
        $prop = new ReflectionProperty(get_class($parent), 'items');

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn([
            'items' => ['mappedBy' => null], // missing mappedBy
        ]);
        $this->em->method('getClassMetadata')->willReturn($meta);

        $args = new TranslationArgs($collection, 'en', 'de')
            ->setTranslatedParent($parent)
            ->setProperty($prop);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not a bidirectional ManyToMany');

        $this->handler->translate($args);
    }
}
