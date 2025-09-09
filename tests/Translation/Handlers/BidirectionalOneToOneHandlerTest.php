<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use ErrorException;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalParent;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\BidirectionalOneToOneHandler;
use Tmi\TranslationBundle\Utils\AttributeHelper;

final class BidirectionalOneToOneHandlerTest extends TestCase
{
    private EntityManagerInterface $em;
    private PropertyAccessor $propertyAccessor;
    private AttributeHelper $attributeHelper;

    public function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->propertyAccessor = new PropertyAccessor();
        $this->attributeHelper = $this->createMock(AttributeHelper::class);
    }

    private function createHandler(): BidirectionalOneToOneHandler
    {
        return new BidirectionalOneToOneHandler(
            $this->em,
            $this->propertyAccessor,
            $this->attributeHelper
        );
    }

    /** ------------------------- Supports ------------------------- */

    public function testSupportsReturnsFalseIfNoProperty(): void
    {
        $handler = $this->createHandler();
        $args = new TranslationArgs(new TranslatableOneToOneBidirectionalParent());
        $args->setProperty(null);

        self::assertFalse($handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsTrueIfOneToOneWithMappedBy(): void
    {
        $handler = $this->createHandler();
        $entity = new TranslatableOneToOneBidirectionalParent();
        $prop = new ReflectionProperty($entity, 'simpleChild');

        $this->attributeHelper->method('isOneToOne')->with($prop)->willReturn(true);

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        self::assertTrue($handler->supports($args));
    }

    /** ------------------------- Shared / Empty -------------------------
     * @throws ReflectionException
     */

    public function testHandleSharedAmongstTranslationsThrows(): void
    {
        $handler = $this->createHandler();
        $entity = new TranslatableOneToOneBidirectionalParent();
        $prop = new ReflectionProperty($entity, 'sharedChild');

        $this->attributeHelper->method('isOneToOne')->with($prop)->willReturn(true);

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessageMatches('/::sharedChild is a Bidirectional OneToOne/');

        $handler->handleSharedAmongstTranslations($args);
    }

    public function testHandleEmptyOnTranslateReturnsNull(): void
    {
        $handler = $this->createHandler();
        $args = new TranslationArgs(new TranslatableOneToOneBidirectionalParent());

        $result = $handler->handleEmptyOnTranslate($args);

        self::assertNull($result);
    }

    /**
     * @throws ReflectionException
     * @throws ErrorException
     */
    public function testHandleSharedAmongstTranslationsReturnsDataIfNotOneToOne(): void
    {
        $handler = $this->createHandler();
        $entity = new TranslatableOneToOneBidirectionalParent();
        $prop = new ReflectionProperty($entity, 'simpleChild');

        $this->attributeHelper->method('isOneToOne')->with($prop)->willReturn(false);

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        $result = $handler->handleSharedAmongstTranslations($args);

        self::assertSame($entity, $result);
    }

    /** ------------------------- Translate -------------------------
     * @throws ReflectionException
     */

    public function testTranslateClonesChildAndSetsParentAndLocale(): void
    {
        $handler = $this->createHandler();

        $parent = new TranslatableOneToOneBidirectionalParent();
        $child = new TranslatableOneToOneBidirectionalChild();
        $parent->setSimpleChild($child);

        $metadata = new ClassMetadata(TranslatableOneToOneBidirectionalChild::class);
        $metadata->associationMappings = [
            'simpleParent' => ['fieldName' => 'simpleParent', 'inversedBy' => 'simpleChild']
        ];

        $this->em->method('getClassMetadata')
            ->with(TranslatableOneToOneBidirectionalChild::class)
            ->willReturn($metadata);

        $prop = new ReflectionProperty($parent, 'simpleChild');

        $args = new TranslationArgs($child, 'en_US', 'it_IT');
        $args->setProperty($prop);
        $args->setTranslatedParent($parent);

        $result = $handler->translate($args);

        self::assertInstanceOf(TranslatableOneToOneBidirectionalChild::class, $result);
        self::assertNotSame($child, $result);
        self::assertSame($parent, $result->getSimpleParent());
        self::assertSame('it_IT', $result->getLocale());
    }
}
