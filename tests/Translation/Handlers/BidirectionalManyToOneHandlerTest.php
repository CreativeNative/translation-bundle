<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Doctrine\ORM\Mapping\ManyToOne;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Exception\SharedAssociationException;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\SharedBackReference\SharedBackReferenceChild;
use Tmi\TranslationBundle\Fixtures\Entity\SharedBackReference\SharedBackReferenceParent;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalParent;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Handlers\BidirectionalManyToOneHandler;
use Tmi\TranslationBundle\ValueObject\Tuuid;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(BidirectionalManyToOneHandler::class)]
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
     * A context without a property is a top-level translate() call, never an
     * association: this handler does not claim it, so its translate() never sees one.
     */
    public function testSupportsReturnsFalseWithoutAProperty(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToManyBidirectionalParent();
        $entity->setLocale('en_US');

        $context = $this->entityContext($entity);
        $context->setTargetLocale('it_IT');

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
     * The direct form: the target is itself translatable, so sharing it would leave the
     * relation's ownership ambiguous across locales -- refused.
     *
     * @throws \ReflectionException
     */
    public function testTranslateThrowsWhenShared(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToManyBidirectionalParent();
        $prop    = new \ReflectionProperty($entity, 'sharedChildren');

        $context = $this->entityContext($entity, $prop)->setShared(true);

        self::expectException(SharedAssociationException::class);
        self::expectExceptionMessageMatches('/::\$sharedChildren is a bidirectional ManyToOne association/');

        $handler->translate($context);
    }

    /**
     * A translated parent on the context is not enough to make it the back-reference
     * form: only the flag BidirectionalOneToManyHandler sets does. Without it the
     * direct-form refusal still applies -- and on a self-referential class the mapping
     * could not have told the two apart anyway (the property IS an association on the
     * entity's own class in both forms).
     *
     * @throws \ReflectionException
     */
    public function testTranslateOfSharedDirectFormStillThrowsWithATranslatedParentPresent(): void
    {
        $handler = $this->createHandler();
        $target  = new SharedBackReferenceParent()->setLocale('en_US');

        $prop    = new \ReflectionProperty(SharedBackReferenceChild::class, 'parent');
        $context = $this->entityContext($target, $prop)->setShared(true);
        $context->setTranslatedParent(new SharedBackReferenceChild());

        self::expectException(SharedAssociationException::class);
        self::expectExceptionMessageMatches('/SharedBackReferenceParent::\$parent is a bidirectional ManyToOne association/');

        $handler->translate($context);
    }

    /**
     * The back-reference form with #[SharedAmongstTranslations] on the child's own
     * ManyToOne (5.1): the property IS the child's FK back to the parent the OneToMany
     * handler is translating, so the child is cloned through the full pipeline and its
     * back-reference resolves to the parent's CLONE -- a distinct instance from the source
     * parent, never the identical one "shared" would otherwise mean.
     *
     * Negative proof: against 5.0.0 this dies in the unconditional isShared() throw.
     *
     * @throws \ReflectionException
     */
    public function testTranslateOfSharedBackReferenceFormResolvesToTheParentClone(): void
    {
        $handler = $this->createHandler();

        $sourceParent = new SharedBackReferenceParent()->setLocale('en_US');
        $parentClone  = new SharedBackReferenceParent()->setLocale('it_IT');
        $child        = new SharedBackReferenceChild()->setLocale('en_US')->setParent($sourceParent);

        $prop    = new \ReflectionProperty($child, 'parent');
        $context = $this->entityContext($child, $prop)->setShared(true)->setBackReference(true);
        $context->setTargetLocale('it_IT');
        $context->setTranslatedParent($parentClone);

        $result = $handler->translate($context);

        self::assertInstanceOf(SharedBackReferenceChild::class, $result);
        self::assertNotSame($child, $result, 'The child must be cloned, not handed back as the shared instance');
        self::assertSame('it_IT', $result->getLocale());
        self::assertSame($parentClone, $result->getParent(), 'The back-reference must point at the parent clone');
        self::assertNotSame($sourceParent, $result->getParent());
        self::assertSame($sourceParent, $child->getParent(), 'The source child is left untouched');
        self::assertFalse($context->isShared(), 'The flag is consumed here, not passed on to TranslatableEntityHandler');
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateReturnsNullWhenEmpty(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToManyBidirectionalParent();
        $prop    = new \ReflectionProperty(TranslatableManyToOneBidirectionalChild::class, 'parentSimple');
        $context = $this->entityContext($entity, $prop)->setEmpty(true);

        $result = $handler->translate($context);
        self::assertThat($result, self::isNull());
    }

    /**
     * Case (b), the back-reference form: the context carries the flag
     * BidirectionalOneToManyHandler sets when it dispatches a child, and 'parentSimple'
     * names the child's own FK back to the parent. The child clone must run the full
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

        $prop    = new \ReflectionProperty($child, 'parentSimple');
        $context = $this->entityContext($child, $prop)->setBackReference(true);
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

        $this->entityManager()->expects($this->never())->method('createQueryBuilder');

        $prop    = new \ReflectionProperty($child, 'parentSimple');
        $context = $this->entityContext($child, $prop)->setBackReference(true);
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
     * DoctrineObjectHandler::translateProperties() on that owner, without the
     * back-reference flag. The old code returned the untranslated source whenever its
     * mapping lookup missed; the target is translated to the matching locale
     * (get-or-create) instead -- there is no back-reference field to repair.
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
     * The direct form with a translated parent present but WITHOUT the flag: the parent
     * on the context is the owner the association was reached through, not something to
     * write into the target -- a self-referential tree is exactly where the old
     * mapping-shape guess got this wrong and wrote the child into the root's `$parent`.
     *
     * @throws \ReflectionException
     */
    public function testTranslateOfDirectFormNeverWritesTheTranslatedParentIntoTheTarget(): void
    {
        $handler = $this->createHandler();

        $target  = new TranslatableOneToManyBidirectionalParent()->setTuuid(Tuuid::generate())->setLocale('en_US');
        $owner   = new TranslatableManyToOneBidirectionalChild()->setLocale('it_IT');
        $prop    = new \ReflectionProperty(TranslatableManyToOneBidirectionalChild::class, 'parentSimple');
        $context = $this->entityContext($target, $prop);
        $context->setTargetLocale('it_IT');
        $context->setTranslatedParent($owner);

        $result = $handler->translate($context);

        self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $result);
        self::assertNotSame($target, $result);
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
    public function testTranslateOfDirectFormNeverQueriesForAnExistingVariant(): void
    {
        $handler = $this->createHandler();

        // See testTranslateOfDirectFormTranslatesTargetInsteadOfReturningSource() for
        // why $target needs an explicit Tuuid here.
        $target = new TranslatableOneToManyBidirectionalParent()->setTuuid(Tuuid::generate())->setLocale('en_US');

        $this->entityManager()->expects($this->never())->method('createQueryBuilder');

        $prop    = new \ReflectionProperty(TranslatableManyToOneBidirectionalChild::class, 'parentSimple');
        $context = $this->entityContext($target, $prop);
        $context->setTargetLocale('it_IT');

        $result = $handler->translate($context);

        self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $result);
        self::assertNotSame($target, $result);
        self::assertSame($target->getTuuid(), $result->getTuuid());
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

        // The delegated TranslatableEntityHandler no longer queries the EntityManager
        // for an existing child variant itself (see its class docblock); the recursive
        // EntityTranslator::processTranslation() call hit while pipelining the child's
        // own back-reference property uses the translator's own LocaleVariantFinder,
        // built on a separate stub (see UnitTestCase::localeVariantFinder()) -- so
        // nothing in this call reaches this entityManager() mock's createQueryBuilder().
        $this->entityManager()->expects($this->never())->method('createQueryBuilder');

        // --- Step 3: Build context ---
        $prop    = new \ReflectionProperty($child, 'parentSimple');
        $context = $this->entityContext($child, $prop);
        $context->setTargetLocale('it_IT');

        $this->translator()->addTranslationHandler($handler);

        // --- Step 4: Translate ---
        $result = $handler->translate($context);

        // --- Step 5: Assertions ---
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
            $this->propertyAccessor(),
            $this->translatableEntityHandler(),
        );
    }
}
