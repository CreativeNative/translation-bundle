<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\ORM\Mapping\OneToManyAssociationMapping;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\NonTranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalParent;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\BidirectionalManyToOneHandler;

#[AllowMockObjectsWithoutExpectations]
final class BidirectionalManyToOneHandlerTest extends UnitTestCase
{
    /** ------------------------- Supports Tests -------------------------.
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseWhenNotManyToOne(): void
    {
        $handler = $this->createHandler();
        $entity  = new Scalar();
        $prop    = new \ReflectionProperty($entity, 'title');

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        $this->attributeHelper()
            ->expects($this->once())
            ->method('isManyToOne')
            ->with($prop)
            ->willReturn(false);

        self::assertFalse($handler->supports($args));
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsTrueWhenManyToOneWithInversedBy(): void
    {
        $handler = $this->createHandler();

        // Inline entity with inversedBy
        $entity = new class implements TranslatableInterface {
            use TranslatableTrait;

            #[ManyToOne(targetEntity: Scalar::class, inversedBy: 'children')]
            public Scalar|null $withInverse = null;

            public function getWithInverse(): Scalar|null
            {
                return $this->withInverse;
            }

            public function setWithInverse(Scalar|null $value): void
            {
                $this->withInverse = $value;
            }
        };

        $prop = new \ReflectionProperty($entity, 'withInverse');

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        $this->attributeHelper()
            ->expects($this->once())
            ->method('isManyToOne')
            ->with($prop)
            ->willReturn(true);

        self::assertTrue($handler->supports($args));
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseWhenNoManyToOneAttributes(): void
    {
        $entity = new Scalar();
        $entity->setLocale('en_US');

        $prop = new \ReflectionProperty($entity::class, 'title');

        $this->attributeHelper()->method('isManyToOne')->with($prop)->willReturn(true);

        $args = new TranslationArgs($entity, 'en_US', 'de_DE')
            ->setProperty($prop)
            ->setTranslatedParent($entity);

        $handler = $this->createHandler();
        self::assertFalse($handler->supports($args));
    }

    /** ------------------------- Shared / Empty Tests -------------------------.
     * @throws \ReflectionException
     */
    public function testHandleSharedAmongstTranslationsThrows(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToManyBidirectionalParent();
        $prop    = new \ReflectionProperty($entity, 'sharedChildren');

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        self::expectException(\ErrorException::class);
        self::expectExceptionMessageMatches('/::sharedChildren is a Bidirectional ManyToOne/');

        $handler->handleSharedAmongstTranslations($args);
    }

    public function testHandleEmptyOnTranslateReturnsNull(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToManyBidirectionalParent();
        $args    = new TranslationArgs($entity);

        $result = $handler->handleEmptyOnTranslate($args);
        self::assertThat($result, self::isNull());
    }

    /** @throws \ReflectionException */
    public function testTranslateWithAssociationMapping(): void
    {
        $handler = $this->createHandler();
        $parent  = new TranslatableOneToManyBidirectionalParent();
        $parent->setLocale('en_US');

        $metadata                      = new ClassMetadata(TranslatableOneToManyBidirectionalParent::class);
        $metadata->associationMappings = [
            'simpleChildren' => new OneToManyAssociationMapping(
                fieldName: 'simpleChildren',
                sourceEntity: TranslatableOneToManyBidirectionalParent::class,
                targetEntity: TranslatableManyToOneBidirectionalChild::class,
            ),
        ];

        $this->entityManager()->method('getClassMetadata')
            ->with(TranslatableOneToManyBidirectionalParent::class)
            ->willReturn($metadata);

        $prop = new \ReflectionProperty($parent, 'simpleChildren');
        $args = new TranslationArgs($parent, 'en_US', 'it_IT');
        $args->setProperty($prop);

        $children = new ArrayCollection([
            new TranslatableManyToOneBidirectionalChild(),
            new TranslatableManyToOneBidirectionalChild(),
        ]);
        $args->setTranslatedParent($children);

        $result = $handler->translate($args);

        self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $result);

        // Translation always produces a clone → must not be the same object
        self::assertNotSame($parent, $result);

        // Locale should be updated
        self::assertSame('it_IT', $result->getLocale());
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateDelegatesToTranslatorIfNoMapping(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToManyBidirectionalParent();

        $metadata                      = new ClassMetadata(TranslatableOneToManyBidirectionalParent::class);
        $metadata->associationMappings = [];

        $this->entityManager()->method('getClassMetadata')
            ->with(TranslatableOneToManyBidirectionalParent::class)
            ->willReturn($metadata);

        $prop = new \ReflectionProperty($entity, 'emptyChildren');
        $args = new TranslationArgs($entity, 'en_US', 'it_IT');
        $args->setProperty($prop);

        $result = $handler->translate($args);

        self::assertSame($entity, $result, 'Should return original entity if no mapping');
    }

    public function testTranslateWithNonTranslatableEntity(): void
    {
        $handler = $this->createHandler();

        $nonTranslatable = new class {
            public string $foo = 'bar';
        };

        $args = new TranslationArgs($nonTranslatable, 'en_US', 'it_IT');

        $result = $handler->translate($args);

        self::assertSame($nonTranslatable, $result, 'Non-translatable entities should be returned as-is');
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateWithNonTranslatableRelatedEntity(): void
    {
        $handler = $this->createHandler();

        // --- Step 1: Create parent entity ---
        $parent = new TranslatableOneToManyBidirectionalParent();
        $parent->setLocale('en_US');

        // --- Step 2: Create non-translatable child ---
        $child = new NonTranslatableManyToOneBidirectionalChild();
        $child->setTitle('non-translatable');
        $child->setParent($parent);

        // --- Step 3: Set up TranslationArgs manually ---
        $prop = new \ReflectionProperty($child, 'parent');
        $args = new TranslationArgs($child, 'en_US', 'it_IT');
        $args->setProperty($prop);

        // --- Step 4: Run translation ---
        $result = $handler->translate($args);

        // --- Step 5: Assertions ---
        self::assertInstanceOf(NonTranslatableManyToOneBidirectionalChild::class, $result);

        // Adjust object identity for non-translatable entities
        self::assertSame($child->getParent(), $result->getParent(), 'Non-translatable parent should remain unchanged');

        // NonTranslatableManyToOneBidirectionalChild does not implement TranslatableInterface
        self::assertSame($child, $result);
    }

    public function testTranslateWithNullProperty(): void
    {
        $handler = $this->createHandler();

        // --- Step 1: Create a parent entity ---
        $entity = new TranslatableOneToManyBidirectionalParent();
        $entity->setLocale('en_US');

        // --- Step 2: Prepare TranslationArgs with null property ---
        $args = new TranslationArgs($entity, 'en_US', 'it_IT');
        $args->setProperty(null);

        // --- Step 3: Translate ---
        $result = $handler->translate($args);

        // --- Step 4: Assertions ---
        self::assertSame($entity, $result, 'Original entity should be returned if property is null');
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateWithTranslatableRelatedEntity(): void
    {
        $handler = $this->createHandler();

        // --- Step 1: Create parent entity (Translatable) ---
        $parent = new TranslatableOneToManyBidirectionalParent();
        $parent->setLocale('en_US');

        // --- Step 2: Create child entity referencing parent ---
        $child = new TranslatableManyToOneBidirectionalChild();
        $child->setLocale('en_US')->setParentSimple($parent);

        // --- Step 3: Association mapping setup ---
        $metadata                      = new ClassMetadata(TranslatableManyToOneBidirectionalChild::class);
        $metadata->associationMappings = [
            'parentSimple' => new ManyToOneAssociationMapping(
                fieldName: 'parentSimple',
                sourceEntity: TranslatableManyToOneBidirectionalChild::class,
                targetEntity: TranslatableOneToManyBidirectionalParent::class,
            ),
        ];
        $this->entityManager()->method('getClassMetadata')
            ->with(TranslatableManyToOneBidirectionalChild::class)
            ->willReturn($metadata);

        // --- Step 4: Build TranslationArgs ---
        $prop = new \ReflectionProperty($child, 'parentSimple');
        $args = new TranslationArgs($child, 'en_US', 'it_IT');
        $args->setProperty($prop);

        $this->translator()->addTranslationHandler($handler);

        // --- Step 6: Translate ---
        $result = $handler->translate($args);

        // --- Step 7: Assertions ---
        self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $result);
        self::assertNotSame($child, $result, 'Child must be cloned');
        self::assertSame('it_IT', $result->getLocale(), 'Child locale should change');

        $resultParent = $result->getParentSimple();
        self::assertInstanceOf(
            TranslatableOneToManyBidirectionalParent::class,
            $resultParent,
            'Parent should also be translated',
        );
        self::assertSame(
            'en_US',
            $resultParent->getLocale(),
            'Parent remains in original locale because no translation exists',
        );
        self::assertSame(
            $parent->getTuuid(),
            $resultParent->getTuuid(),
            'Parent translation must keep same tuuid',
        );
    }

    private function createHandler(): BidirectionalManyToOneHandler
    {
        return new BidirectionalManyToOneHandler(
            $this->attributeHelper(),
            $this->entityManager(),
            $this->propertyAccessor(),
            $this->translator(),
        );
    }
}
