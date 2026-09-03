<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\OneToOneOwningSideMapping;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalParent;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Handlers\BidirectionalOneToOneHandler;

#[AllowMockObjectsWithoutExpectations]
final class BidirectionalOneToOneHandlerTest extends UnitTestCase
{
    /** ------------------------- Supports ------------------------- */
    public function testSupportsReturnsFalseIfNoProperty(): void
    {
        $handler = $this->createHandler();
        $context = $this->entityContext(new TranslatableOneToOneBidirectionalParent());

        self::assertFalse($handler->supports($context));
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsTrueIfOneToOneWithMappedBy(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToOneBidirectionalParent();
        $prop    = new \ReflectionProperty($entity, 'simpleChild');

        $this->attributeHelper()->method('isOneToOne')->with($prop)->willReturn(true);

        $context = $this->entityContext($entity, $prop);

        self::assertTrue($handler->supports($context));
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseWhenNoOneToOneAttributePresent(): void
    {
        $handler = $this->createHandler();

        // 1. Use a TranslatableInterface entity
        $entity = new Scalar();
        $entity->setLocale('en_US');

        // 2. Pick a property that exists but has NO #[OneToOne] attribute
        $prop = new \ReflectionProperty($entity::class, 'title'); // title is a plain string

        // 3. Mock AttributeHelper to return true for isOneToOne
        $this->attributeHelper()->method('isOneToOne')->with($prop)->willReturn(true);

        // 4. Build the context
        $context = $this->entityContext($entity, $prop);
        $context->setTranslatedParent($entity);

        // 5. Assert that supports() hits the empty attribute branch
        self::assertFalse($handler->supports($context));
    }

    /** ------------------------- Shared / Empty -------------------------.
     * @throws \ReflectionException
     */
    public function testTranslateThrowsWhenShared(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToOneBidirectionalParent();
        $prop    = new \ReflectionProperty($entity, 'sharedChild');

        $this->attributeHelper()->method('isOneToOne')->with($prop)->willReturn(true);

        $context = $this->entityContext($entity, $prop)->setShared(true);

        self::expectException(\ErrorException::class);
        self::expectExceptionMessageMatches('/::sharedChild is a Bidirectional OneToOne/');

        $handler->translate($context);
    }

    public function testTranslateReturnsNullWhenEmpty(): void
    {
        $handler = $this->createHandler();
        $context = $this->entityContext(new TranslatableOneToOneBidirectionalParent())->setEmpty(true);

        $result = $handler->translate($context);

        self::assertThat($result, self::isNull());
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateSharedReturnsDataIfNotOneToOne(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToOneBidirectionalParent();
        $prop    = new \ReflectionProperty($entity, 'simpleChild');

        $this->attributeHelper()->method('isOneToOne')->with($prop)->willReturn(false);

        $context = $this->entityContext($entity, $prop)->setShared(true);

        $result = $handler->translate($context);

        self::assertSame($entity, $result);
    }

    /** ------------------------- Translate -------------------------.
     * @throws \ReflectionException
     */
    public function testTranslateRunsFullPipelineOnNewChildCloneAndSetsParentAndLocale(): void
    {
        $handler = $this->createHandler();

        $parent = new TranslatableOneToOneBidirectionalParent();
        $child  = new TranslatableOneToOneBidirectionalChild();
        $parent->setSimpleChild($child);

        // Simulate a persisted row: a shallow `clone $data` would have copied this id
        // verbatim onto the clone instead of resetting it.
        $idProperty = new \ReflectionProperty(TranslatableOneToOneBidirectionalChild::class, 'id');
        $idProperty->setValue($child, 42);

        $metadata = new ClassMetadata(TranslatableOneToOneBidirectionalChild::class);
        $mapping  = new OneToOneOwningSideMapping(
            fieldName: 'simpleParent',
            sourceEntity: TranslatableOneToOneBidirectionalChild::class,
            targetEntity: TranslatableOneToOneBidirectionalParent::class,
        );
        $mapping->inversedBy           = 'simpleChild';
        $metadata->associationMappings = [
            'simpleParent' => $mapping,
        ];

        $this->entityManager()->method('getClassMetadata')
            ->with(TranslatableOneToOneBidirectionalChild::class)
            ->willReturn($metadata);

        // $child's own simpleParent field is never set here (only the parent's
        // simpleChild side is), so translateProperties() finds nothing to recurse into
        // for it -- and the delegated TranslatableEntityHandler no longer queries for
        // the child itself (see its class docblock), so nothing in this call reaches
        // the EntityManager for an existence check at all.
        $this->entityManager()->expects($this->never())->method('createQueryBuilder');

        $prop = new \ReflectionProperty($parent, 'simpleChild');

        $context = $this->entityContext($child, $prop);
        $context->setTargetLocale('it_IT');
        $context->setTranslatedParent($parent);

        $result = $handler->translate($context);

        self::assertInstanceOf(TranslatableOneToOneBidirectionalChild::class, $result);
        self::assertNotSame($child, $result);
        self::assertNull($result->getId(), 'Generated id must be reset on the clone, not copied verbatim from the source row');
        self::assertSame($parent, $result->getSimpleParent());
        self::assertSame('it_IT', $result->getLocale());
    }

    /**
     * Existence is resolved once, before this handler runs, by
     * EntityTranslator::processTranslation() -- the delegated TranslatableEntityHandler
     * never queries the EntityManager for an existing variant itself (see its class
     * docblock), so calling this handler directly, as this test does, always clones.
     *
     * @throws \ReflectionException
     */
    public function testTranslateNeverQueriesForAnExistingVariantAndRepairsBackReference(): void
    {
        $handler = $this->createHandler();

        $parent = new TranslatableOneToOneBidirectionalParent();
        $child  = new TranslatableOneToOneBidirectionalChild();
        $parent->setSimpleChild($child);

        $metadata = new ClassMetadata(TranslatableOneToOneBidirectionalChild::class);
        $mapping  = new OneToOneOwningSideMapping(
            fieldName: 'simpleParent',
            sourceEntity: TranslatableOneToOneBidirectionalChild::class,
            targetEntity: TranslatableOneToOneBidirectionalParent::class,
        );
        $mapping->inversedBy           = 'simpleChild';
        $metadata->associationMappings = [
            'simpleParent' => $mapping,
        ];

        $this->entityManager()->method('getClassMetadata')
            ->with(TranslatableOneToOneBidirectionalChild::class)
            ->willReturn($metadata);

        $this->entityManager()->expects($this->never())->method('createQueryBuilder');

        $prop = new \ReflectionProperty($parent, 'simpleChild');

        $context = $this->entityContext($child, $prop);
        $context->setTargetLocale('it_IT');
        $context->setTranslatedParent($parent);

        $result = $handler->translate($context);

        self::assertInstanceOf(TranslatableOneToOneBidirectionalChild::class, $result);
        self::assertNotSame($child, $result);
        self::assertSame($parent, $result->getSimpleParent(), 'Back-reference must be repaired on the clone');
    }

    private function createHandler(): BidirectionalOneToOneHandler
    {
        return new BidirectionalOneToOneHandler(
            $this->entityManager(),
            $this->propertyAccessor(),
            $this->attributeHelper(),
            $this->translatableEntityHandler(),
        );
    }
}
