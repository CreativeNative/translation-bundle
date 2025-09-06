<?php
declare(strict_types=1);

namespace TMI\TranslationBundle\Test\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToMany;
use ErrorException;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyUnidirectionalChild;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyUnidirectionalParent;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslatorInterface;
use TMI\TranslationBundle\Translation\Handlers\UnidirectionalManyToManyHandler;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * @covers \TMI\TranslationBundle\Translation\Handlers\UnidirectionalManyToManyHandler
 */
final class UnidirectionalManyToManyHandlerTest extends TestCase
{
    private AttributeHelper $attributeHelper;
    private EntityManagerInterface $em;
    private EntityTranslatorInterface $translator;

    public function setUp(): void
    {
        parent::setUp();

        $this->attributeHelper = $this->createMock(AttributeHelper::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->translator = new class implements EntityTranslatorInterface {
            public function translate(TranslatableInterface $entity, string $locale): TranslatableInterface
            {
                $clone = clone $entity;
                $clone->setLocale($locale);
                return $clone;
            }
            public function afterLoad(TranslatableInterface $entity): void {}
            public function beforePersist(TranslatableInterface $entity, EntityManagerInterface $em): void {}
            public function beforeUpdate(TranslatableInterface $entity, EntityManagerInterface $em): void {}
            public function beforeRemove(TranslatableInterface $entity, EntityManagerInterface $em): void {}
        };
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsFalseWhenAttributeHelperReportsNotManyToMany(): void
    {
        // Reflect the property we want to test (simpleChildren).
        $parent = new TranslatableManyToManyUnidirectionalParent();
        $prop = new ReflectionProperty($parent::class, 'simpleChildren');

        // This test specifically wants attributeHelper->isManyToMany(...) to return FALSE.
        $this->attributeHelper->method('isManyToMany')->willReturn(false);

        $args = new TranslationArgs($parent->getSimpleChildren(), 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($parent);

        $handler = new UnidirectionalManyToManyHandler(
            $this->attributeHelper,
            $this->translator,
            $this->em
        );

        self::assertFalse($handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsFalseWhenNoManyToManyAttributePresent(): void
    {
        // Make sure attributeHelper returns true so supports() proceeds to check attributes()
        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        // Anonymous object with a plain property that has NO PHP attribute
        $anon = new class { public array $plain = []; };
        $prop = new ReflectionProperty($anon::class, 'plain');

        // IMPORTANT: pass a Collection instance (not the raw array). If you pass a plain array,
        // supports() will return false immediately and you won't exercise the attributes() branch.
        $collection = new ArrayCollection($anon->plain);

        $args = new TranslationArgs($collection, 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($anon);

        $handler = new UnidirectionalManyToManyHandler($this->attributeHelper, $this->translator, $this->em);

        // Sanity check (optional) to prove ReflectionProperty has no ManyToMany attribute
        self::assertSame([], $prop->getAttributes(ManyToMany::class));

        self::assertFalse($handler->supports($args));
    }

    public function testHandleSharedAmongstTranslationsFallsBackToTranslatePath(): void
    {
        // If property is null -> the handler's "if ($property !== null && isManyToMany)" doesn't trigger,
        // so handleSharedAmongstTranslations will call translate($args) (and translate throws).
        $handler = new UnidirectionalManyToManyHandler($this->attributeHelper, $this->translator, $this->em);

        $args = new TranslationArgs(new ArrayCollection(), 'en', 'de');
        // no translatedParent and no property -> translate should throw "No translated parent provided."
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No translated parent provided');

        $handler->handleSharedAmongstTranslations($args);
    }

    public function testTranslateThrowsWhenNoTranslatedParent(): void
    {
        $handler = new UnidirectionalManyToManyHandler($this->attributeHelper, $this->translator, $this->em);

        $args = new TranslationArgs(new ArrayCollection(), 'en', 'de');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No translated parent provided');

        $handler->translate($args);
    }

    public function testTranslateThrowsWhenNoPropertyGiven(): void
    {
        $handler = new UnidirectionalManyToManyHandler($this->attributeHelper, $this->translator, $this->em);

        $parent = new class { public ?string $any = null; };
        $args = new TranslationArgs(new ArrayCollection(), 'en', 'de')
            ->setTranslatedParent($parent);
        // property not set -> should throw
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No property given for parent of class');

        $handler->translate($args);
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateThrowsWhenAssociationNotFound(): void
    {
        $parent = new class { public ?array $items = null; };
        $prop = new ReflectionProperty($parent::class, 'items');

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn([]); // no mapping
        $this->em->method('getClassMetadata')->with($parent::class)->willReturn($meta);

        $handler = new UnidirectionalManyToManyHandler($this->attributeHelper, $this->translator, $this->em);

        $args = new TranslationArgs(new ArrayCollection(), 'en', 'de')
            ->setTranslatedParent($parent)
            ->setProperty($prop);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not a valid association');

        $handler->translate($args);
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateThrowsWhenNotOwningSide(): void
    {
        $parent = new class { public ?array $items = null; };
        $prop = new ReflectionProperty($parent::class, 'items');

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn([
            'items' => ['fieldName' => 'items', 'isOwningSide' => false],
        ]);
        $this->em->method('getClassMetadata')->with($parent::class)->willReturn($meta);

        $handler = new UnidirectionalManyToManyHandler($this->attributeHelper, $this->translator, $this->em);

        $args = new TranslationArgs(new ArrayCollection(), 'en', 'de')
            ->setTranslatedParent($parent)
            ->setProperty($prop);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not the owning side');

        $handler->translate($args);
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateThrowsWhenFieldNotFoundOnOwner(): void
    {
        $parent = new class { /* note: no "missingField" property */ public ?array $items = null; };
        $prop = new ReflectionProperty($parent::class, 'items');

        $meta = $this->createMock(ClassMetadata::class);
        // mapping says fieldName is 'missingField' which the parent does not have -> triggers field-not-found exception
        $meta->method('getAssociationMappings')->willReturn([
            'items' => ['fieldName' => 'missingField', 'isOwningSide' => true],
        ]);
        $this->em->method('getClassMetadata')->with($parent::class)->willReturn($meta);

        $handler = new UnidirectionalManyToManyHandler($this->attributeHelper, $this->translator, $this->em);

        $args = new TranslationArgs(new ArrayCollection(), 'en', 'de')
            ->setTranslatedParent($parent)
            ->setProperty($prop);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Field "missingField" not found in class');

        $handler->translate($args);
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateCreatesCollectionWhenFieldIsNotCollectionAndAddsTranslatedItems(): void
    {
        // parent with public property "items" which is null initially (not a Collection)
        $parent = new class {
            public $items = null; // intentionally not a Collection
        };

        // property representing the association; key must match mapping key (we use 'items')
        $prop = new ReflectionProperty($parent::class, 'items');

        $child1 = new TranslatableManyToManyUnidirectionalChild();
        $child1->setLocale('en')->setTuuid('t-1');
        $child2 = new TranslatableManyToManyUnidirectionalChild();
        $child2->setLocale('en')->setTuuid('t-2');

        $data = new ArrayCollection([$child1, $child2]);

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn([
            // mapping key is the property name used by the handler ($prop->name)
            'items' => ['fieldName' => 'items', 'isOwningSide' => true],
        ]);
        $this->em->method('getClassMetadata')->with($parent::class)->willReturn($meta);

        // This test requires attributeHelper to report that the property *is* ManyToMany
        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        $handler = new UnidirectionalManyToManyHandler($this->attributeHelper, $this->translator, $this->em);

        $args = new TranslationArgs($data, 'en', 'de')
            ->setTranslatedParent($parent)
            ->setProperty($prop);

        $result = $handler->translate($args);

        // result should be a Collection with translated (cloned) children
        self::assertInstanceOf(Collection::class, $result);
        self::assertCount(2, $result);

        // owner property should now contain a Collection with same items
        self::assertInstanceOf(Collection::class, $parent->items);
        self::assertCount(2, $parent->items);

        foreach ($result as $item) {
            self::assertSame('de', $item->getLocale());
        }
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsTrueForUnidirectionalManyToManyProperty(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();
        $prop = new ReflectionProperty($parent::class, 'simpleChildren');

        // this test needs attributeHelper to claim the prop is ManyToMany
        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        $args = new TranslationArgs($parent->getSimpleChildren(), 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($parent);

        $handler = new UnidirectionalManyToManyHandler($this->attributeHelper, $this->translator, $this->em);

        self::assertTrue($handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateReplacesCollectionWithTranslatedItems(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();
        $child1 = new TranslatableManyToManyUnidirectionalChild()
            ->setLocale('en')
            ->setTuuid(uniqid('tu1-', true));
        $child2 = new TranslatableManyToManyUnidirectionalChild()
            ->setLocale('en')
            ->setTuuid(uniqid('tu2-', true));

        $parent->addSimpleChild($child1)->addSimpleChild($child2);

        $prop = new ReflectionProperty($parent::class, 'simpleChildren');

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn([
            'simpleChildren' => ['fieldName' => 'simpleChildren', 'isOwningSide' => true],
        ]);
        $this->em->method('getClassMetadata')->with($parent::class)->willReturn($meta);

        // ensure attributeHelper returns true for this test
        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        $handler = new UnidirectionalManyToManyHandler($this->attributeHelper, $this->translator, $this->em);

        $args = new TranslationArgs($parent->getSimpleChildren(), 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($parent);

        $result = $handler->translate($args);

        self::assertCount(2, $result, 'Translated collection should contain 2 items');

        foreach ($result as $item) {
            self::assertInstanceOf(TranslatableManyToManyUnidirectionalChild::class, $item);
            self::assertSame('de', $item->getLocale());
        }
    }

    /**
     * @throws ReflectionException
     */
    public function testHandleSharedAmongstTranslationsThrowsForManyToMany(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();
        $child = new TranslatableManyToManyUnidirectionalChild()
            ->setLocale('en')
            ->setTuuid(uniqid('tuuid-', true));
        $parent->addSharedChild($child);

        $prop = new ReflectionProperty($parent::class, 'sharedChildren');

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getAssociationMappings')->willReturn([
            'sharedChildren' => ['fieldName' => 'sharedChildren', 'isOwningSide' => true],
        ]);
        $this->em->method('getClassMetadata')->with($parent::class)->willReturn($meta);

        // attributeHelper must indicate this is a ManyToMany for the handler to throw
        $this->attributeHelper->method('isManyToMany')->willReturn(true);

        $handler = new UnidirectionalManyToManyHandler($this->attributeHelper, $this->translator, $this->em);

        $args = new TranslationArgs($parent->getSharedChildren(), 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($parent);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('SharedAmongstTranslations is not supported for ManyToMany associations');

        $handler->handleSharedAmongstTranslations($args);
    }
}
