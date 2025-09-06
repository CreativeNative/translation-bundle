<?php
declare(strict_types=1);

namespace TMI\TranslationBundle\Test\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use ErrorException;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
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
        $this->attributeHelper->method('isManyToMany')->willReturn(true);

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
    public function testSupportsReturnsTrueForUnidirectionalManyToManyProperty(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();
        $prop = new ReflectionProperty($parent::class, 'simpleChildren');

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

        $handler = new UnidirectionalManyToManyHandler($this->attributeHelper, $this->translator, $this->em);

        $args = new TranslationArgs($parent->getSharedChildren(), 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($parent);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('SharedAmongstTranslations is not supported for ManyToMany associations');

        $handler->handleSharedAmongstTranslations($args);
    }
}
