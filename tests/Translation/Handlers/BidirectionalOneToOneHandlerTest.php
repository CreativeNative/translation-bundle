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
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\BidirectionalOneToOneHandler;
use Tmi\TranslationBundle\Utils\AttributeHelper;

#[AllowMockObjectsWithoutExpectations]
final class BidirectionalOneToOneHandlerTest extends UnitTestCase
{
    /** ------------------------- Supports ------------------------- */
    public function testSupportsReturnsFalseIfNoProperty(): void
    {
        $handler = $this->createHandler();
        $args    = new TranslationArgs(new TranslatableOneToOneBidirectionalParent());
        $args->setProperty(null);

        self::assertFalse($handler->supports($args));
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

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        self::assertTrue($handler->supports($args));
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

        // 4. Create TranslationArgs
        $args = new TranslationArgs($entity, 'en_US', 'de_DE')
            ->setProperty($prop)
            ->setTranslatedParent($entity);

        // 5. Assert that supports() hits the empty attribute branch
        self::assertFalse($handler->supports($args));
    }

    /** ------------------------- Shared / Empty -------------------------.
     * @throws \ReflectionException
     */
    public function testHandleSharedAmongstTranslationsThrows(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToOneBidirectionalParent();
        $prop    = new \ReflectionProperty($entity, 'sharedChild');

        $this->attributeHelper()->method('isOneToOne')->with($prop)->willReturn(true);

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        self::expectException(\ErrorException::class);
        self::expectExceptionMessageMatches('/::sharedChild is a Bidirectional OneToOne/');

        $handler->handleSharedAmongstTranslations($args);
    }

    public function testHandleEmptyOnTranslateReturnsNull(): void
    {
        $handler = $this->createHandler();
        $args    = new TranslationArgs(new TranslatableOneToOneBidirectionalParent());

        $result = $handler->handleEmptyOnTranslate($args);

        self::assertThat($result, self::isNull());
    }

    /**
     * @throws \ReflectionException
     * @throws \ErrorException
     */
    public function testHandleSharedAmongstTranslationsReturnsDataIfNotOneToOne(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToOneBidirectionalParent();
        $prop    = new \ReflectionProperty($entity, 'simpleChild');

        $this->attributeHelper()->method('isOneToOne')->with($prop)->willReturn(false);

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        $result = $handler->handleSharedAmongstTranslations($args);

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

        // No existing de_DE/it_IT variant -- the finder's query returns nothing, so the
        // delegated TranslatableEntityHandler takes the create path.
        $this->entityManager()->method('createQueryBuilder')
            ->willReturn($this->queryBuilderReturning([]));

        $prop = new \ReflectionProperty($parent, 'simpleChild');

        $args = new TranslationArgs($child, 'en_US', 'it_IT');
        $args->setProperty($prop);
        $args->setTranslatedParent($parent);

        $result = $handler->translate($args);

        self::assertInstanceOf(TranslatableOneToOneBidirectionalChild::class, $result);
        self::assertNotSame($child, $result);
        self::assertNull($result->getId(), 'Generated id must be reset on the clone, not copied verbatim from the source row');
        self::assertSame($parent, $result->getSimpleParent());
        self::assertSame('it_IT', $result->getLocale());
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateReusesExistingChildVariantAndRepairsBackReference(): void
    {
        $handler = $this->createHandler();

        $parent = new TranslatableOneToOneBidirectionalParent();
        $child  = new TranslatableOneToOneBidirectionalChild();
        $parent->setSimpleChild($child);

        $existingTranslation = new TranslatableOneToOneBidirectionalChild()->setLocale('it_IT');

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

        // The finder's query reports an already-translated row for this tuuid/locale.
        $this->entityManager()->method('createQueryBuilder')
            ->willReturn($this->queryBuilderReturning([$existingTranslation]));

        $prop = new \ReflectionProperty($parent, 'simpleChild');

        $args = new TranslationArgs($child, 'en_US', 'it_IT');
        $args->setProperty($prop);
        $args->setTranslatedParent($parent);

        $result = $handler->translate($args);

        // The existing variant is returned as-is -- not a fresh clone. A handler that
        // skipped the finder check here would mint a second (tuuid, locale) row on every
        // call instead of reusing the one already in the database.
        self::assertSame($existingTranslation, $result);
        self::assertSame($parent, $result->getSimpleParent(), 'Back-reference must be repaired even on a reused variant');
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
