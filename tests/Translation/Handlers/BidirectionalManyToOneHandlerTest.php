<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalParent;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Handlers\BidirectionalManyToOneHandler;
use Tmi\TranslationBundle\ValueObject\Tuuid;

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

        $context = $this->entityContext($entity, $prop);

        $this->attributeHelper()
            ->expects($this->once())
            ->method('isManyToOne')
            ->with($prop)
            ->willReturn(false);

        self::assertFalse($handler->supports($context));
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsFalseWhenNotEntityContext(): void
    {
        $handler = $this->createHandler();
        $entity  = new Scalar();
        $prop    = new \ReflectionProperty($entity, 'title');

        // A ManyToOne property's value is always translated as an EntityTranslationContext
        // (see DoctrineObjectHandler::translateProperties()) -- a PropertyTranslationContext
        // never carries a TranslatableInterface value in practice.
        $context = $this->propertyContext($entity, $prop);

        self::assertFalse($handler->supports($context));
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

        $context = $this->entityContext($entity, $prop);

        $this->attributeHelper()
            ->expects($this->once())
            ->method('isManyToOne')
            ->with($prop)
            ->willReturn(true);

        self::assertTrue($handler->supports($context));
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

        $context = $this->entityContext($entity, $prop);
        $context->setTranslatedParent($entity);

        $handler = $this->createHandler();
        self::assertFalse($handler->supports($context));
    }

    /** ------------------------- Shared / Empty Tests -------------------------.
     * @throws \ReflectionException
     */
    public function testTranslateThrowsWhenShared(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToManyBidirectionalParent();
        $prop    = new \ReflectionProperty($entity, 'sharedChildren');

        $context = $this->entityContext($entity, $prop)->setShared(true);

        self::expectException(\ErrorException::class);
        self::expectExceptionMessageMatches('/::sharedChildren is a Bidirectional ManyToOne/');

        $handler->translate($context);
    }

    public function testTranslateReturnsNullWhenEmpty(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToManyBidirectionalParent();
        $context = $this->entityContext($entity)->setEmpty(true);

        $result = $handler->translate($context);
        self::assertThat($result, self::isNull());
    }

    /**
     * Case (b), the back-reference form: $propertyName ('parentSimple') names a ManyToOne
     * association declared on the entity's own class -- exactly how
     * BidirectionalOneToManyHandler dispatches a child. The child clone must run the full
     * entity pipeline (not a shallow clone) and its back-reference gets repaired to the
     * parent this handler already knows is being translated.
     *
     * @throws \ReflectionException
     */
    public function testTranslateRunsFullPipelineOnBackReferenceFormAndRepairsParent(): void
    {
        $handler = $this->createHandler();

        $parent = new TranslatableOneToManyBidirectionalParent();
        $child  = new TranslatableManyToOneBidirectionalChild();
        $child->setLocale('en_US')->setParentSimple($parent);

        // Simulate a persisted row: a shallow `clone $entity` would have copied this id
        // verbatim onto the clone instead of resetting it.
        $idProperty = new \ReflectionProperty(TranslatableManyToOneBidirectionalChild::class, 'id');
        $idProperty->setValue($child, 7);

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

        $prop    = new \ReflectionProperty($child, 'parentSimple');
        $context = $this->entityContext($child, $prop);
        $context->setTargetLocale('it_IT');
        $context->setTranslatedParent($parent);

        $result = $handler->translate($context);

        self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $result);
        self::assertNotSame($child, $result);
        self::assertNull($result->getId(), 'Generated id must be reset on the clone, not copied verbatim from the source row');
        self::assertSame($parent, $result->getParentSimple());
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
    public function testTranslateOnBackReferenceFormNeverQueriesForAnExistingVariant(): void
    {
        $handler = $this->createHandler();

        $parent = new TranslatableOneToManyBidirectionalParent();
        $child  = new TranslatableManyToOneBidirectionalChild();
        $child->setLocale('en_US')->setParentSimple($parent);

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

        $this->entityManager()->expects($this->never())->method('createQueryBuilder');

        $prop    = new \ReflectionProperty($child, 'parentSimple');
        $context = $this->entityContext($child, $prop);
        $context->setTargetLocale('it_IT');
        $context->setTranslatedParent($parent);

        $result = $handler->translate($context);

        self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $result);
        self::assertNotSame($child, $result);
        self::assertSame($parent, $result->getParentSimple(), 'Back-reference must be repaired on the clone');
    }

    /**
     * Case (a), the direct form: the property ('parentSimple') is declared on a different,
     * owning class than the entity being translated -- reached via
     * DoctrineObjectHandler::translateProperties() on that owner, borrowed here via
     * reflection on the Child fixture's own field to reproduce the exact mismatch (the
     * target's own metadata never has a field literally named after another class's
     * property). The old code returned the untranslated source whenever this lookup missed;
     * the fix instead translates the target to the matching locale (get-or-create) -- there
     * is no back-reference field to repair.
     *
     * @throws \ReflectionException
     */
    public function testTranslateOfDirectFormTranslatesTargetInsteadOfReturningSource(): void
    {
        $handler = $this->createHandler();

        // An explicit Tuuid: TranslatableEntityHandler no longer forces the source's
        // Tuuid to generate before cloning (that side effect of the removed finder
        // lookup is gone -- see its class docblock), and TranslatableTrait::getTuuid()
        // lazily generates a fresh one per still-null instance, so an unset Tuuid here
        // would let the clone and $target each generate their own.
        $target = new TranslatableOneToManyBidirectionalParent()->setTuuid(Tuuid::generate())->setLocale('en_US');

        $metadata                      = new ClassMetadata(TranslatableOneToManyBidirectionalParent::class);
        $metadata->associationMappings = [];

        $this->entityManager()->method('getClassMetadata')
            ->with(TranslatableOneToManyBidirectionalParent::class)
            ->willReturn($metadata);

        $prop    = new \ReflectionProperty(TranslatableManyToOneBidirectionalChild::class, 'parentSimple');
        $context = $this->entityContext($target, $prop);
        $context->setTargetLocale('it_IT');

        $result = $handler->translate($context);

        self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $result);
        self::assertNotSame($target, $result, 'The direct form must translate the target instead of returning the untranslated source');
        self::assertSame('it_IT', $result->getLocale());
        self::assertSame($target->getTuuid(), $result->getTuuid());
    }

    /**
     * Existence is resolved once, before this handler runs, by
     * EntityTranslator::processTranslation() -- the delegated TranslatableEntityHandler
     * never queries the EntityManager for an existing variant itself (see its class
     * docblock), so calling this handler directly, as this test does, always clones.
     *
     * @throws \ReflectionException
     */
    public function testTranslateOfDirectFormNeverQueriesForAnExistingVariant(): void
    {
        $handler = $this->createHandler();

        // See testTranslateOfDirectFormTranslatesTargetInsteadOfReturningSource() for
        // why $target needs an explicit Tuuid here.
        $target                        = new TranslatableOneToManyBidirectionalParent()->setTuuid(Tuuid::generate())->setLocale('en_US');
        $metadata                      = new ClassMetadata(TranslatableOneToManyBidirectionalParent::class);
        $metadata->associationMappings = [];

        $this->entityManager()->method('getClassMetadata')
            ->with(TranslatableOneToManyBidirectionalParent::class)
            ->willReturn($metadata);

        $this->entityManager()->expects($this->never())->method('createQueryBuilder');

        $prop    = new \ReflectionProperty(TranslatableManyToOneBidirectionalChild::class, 'parentSimple');
        $context = $this->entityContext($target, $prop);
        $context->setTargetLocale('it_IT');

        $result = $handler->translate($context);

        self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $result);
        self::assertNotSame($target, $result);
        self::assertSame($target->getTuuid(), $result->getTuuid());
    }

    public function testTranslateWithNullProperty(): void
    {
        $handler = $this->createHandler();

        // --- Step 1: Create a parent entity ---
        $entity = new TranslatableOneToManyBidirectionalParent();
        $entity->setLocale('en_US');

        // --- Step 2: Context with no property set ---
        $context = $this->entityContext($entity);
        $context->setTargetLocale('it_IT');

        // --- Step 3: Translate ---
        $result = $handler->translate($context);

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

        // The delegated TranslatableEntityHandler no longer queries the EntityManager
        // for an existing child variant itself (see its class docblock); the recursive
        // EntityTranslator::processTranslation() call hit while pipelining the child's
        // own back-reference property uses the translator's own LocaleVariantFinder,
        // built on a separate stub (see UnitTestCase::localeVariantFinder()) -- so
        // nothing in this call reaches this entityManager() mock's createQueryBuilder().
        $this->entityManager()->expects($this->never())->method('createQueryBuilder');

        // --- Step 4: Build context ---
        $prop    = new \ReflectionProperty($child, 'parentSimple');
        $context = $this->entityContext($child, $prop);
        $context->setTargetLocale('it_IT');

        $this->translator()->addTranslationHandler($handler);

        // --- Step 6: Translate ---
        $result = $handler->translate($context);

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
            $this->translatableEntityHandler(),
        );
    }
}
