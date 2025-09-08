<?php

declare(strict_types=1);

namespace TMI\TranslationBundle\Test\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use ErrorException;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalChild;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalParent;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslatorInterface;
use TMI\TranslationBundle\Translation\Handlers\BidirectionalOneToManyHandler;
use TMI\TranslationBundle\Utils\AttributeHelper;

final class BidirectionalOneToManyHandlerTest extends TestCase
{
    private AttributeHelper $attributeHelper;
    private EntityTranslatorInterface $translator;
    private EntityManagerInterface $em;

    public function setUp(): void
    {
        $this->attributeHelper = $this->createMock(AttributeHelper::class);
        $this->translator = new class implements EntityTranslatorInterface {
            public function translate(TranslatableInterface $entity, string $locale): TranslatableInterface
            {
                $clone = clone $entity;
                $clone->setLocale($locale);
                return $clone;
            }
            public function processTranslation(TranslationArgs $args): mixed
            {
                $clone = clone $args->getDataToBeTranslated();
                if (method_exists($clone, 'setLocale') && $args->getTargetLocale()) {
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

        $this->em = $this->createMock(EntityManagerInterface::class);
    }

    private function createHandler(): BidirectionalOneToManyHandler
    {
        return new BidirectionalOneToManyHandler(
            $this->attributeHelper,
            $this->translator,
            $this->em
        );
    }

    /** ------------------------- Supports -------------------------
     * @throws ReflectionException
     */

    public function testSupportsReturnsFalseWhenNotOneToMany(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToManyBidirectionalParent();
        $prop    = new ReflectionProperty($entity, 'children');

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        $this->attributeHelper->method('isOneToMany')->with($prop)->willReturn(false);

        self::assertFalse($handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsTrueWhenOneToManyWithMappedBy(): void
    {
        $handler = $this->createHandler();

        $entity = new TranslatableOneToManyBidirectionalParent();
        $prop = new ReflectionProperty($entity, 'children');

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        $this->attributeHelper->method('isOneToMany')->with($prop)->willReturn(true);

        self::assertTrue($handler->supports($args));
    }

    /** ------------------------- Shared / Empty -------------------------
     * @throws ReflectionException
     */

    public function testHandleSharedAmongstTranslationsThrows(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToManyBidirectionalParent();
        $prop    = new ReflectionProperty($entity, 'children');

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessageMatches('/::children is a Bidirectional OneToMany/');

        $handler->handleSharedAmongstTranslations($args);
    }

    public function testHandleEmptyOnTranslateReturnsArrayCollection(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableOneToManyBidirectionalParent();
        $args    = new TranslationArgs($entity);

        $result = $handler->handleEmptyOnTranslate($args);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(0, $result);
    }

    /** ------------------------- Translate -------------------------
     * @throws ReflectionException
     */

    public function testTranslateClonesCollectionAndProcessesChildren(): void
    {
        $handler = $this->createHandler();

        $parent = new TranslatableOneToManyBidirectionalParent();
        $child1 = new TranslatableOneToManyBidirectionalChild();
        $child2 = new TranslatableOneToManyBidirectionalChild();

        $parent->setChildren(new ArrayCollection([$child1, $child2]));

        $metadata = new ClassMetadata(TranslatableOneToManyBidirectionalParent::class);
        $metadata->associationMappings = [
            'children' => ['mappedBy' => 'parent']
        ];

        $this->em->method('getClassMetadata')
            ->with(TranslatableOneToManyBidirectionalParent::class)
            ->willReturn($metadata);

        $collection = $parent->getChildren();

        $args = new TranslationArgs($collection, 'en_US', 'it_IT');
        $args->setProperty(new ReflectionProperty($parent, 'children'));
        $args->setTranslatedParent($parent);

        $result = $handler->translate($args);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(2, $result);
        foreach ($result as $child) {
            self::assertSame($parent, $child->getParent());
        }
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateReturnsEmptyCollectionWhenNoParentOrProperty(): void
    {
        $handler = $this->createHandler();
        $collection = new ArrayCollection([new TranslatableOneToManyBidirectionalChild()]);

        $args = new TranslationArgs($collection, 'en_US', 'fr_FR');
        $args->setProperty(null);
        $args->setTranslatedParent(null);

        $result = $handler->translate($args);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(0, $result);
    }
}
