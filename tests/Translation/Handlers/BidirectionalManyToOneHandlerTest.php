<?php

declare(strict_types=1);

namespace TMI\TranslationBundle\Test\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToOne;
use ErrorException;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOne;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslatorInterface;
use TMI\TranslationBundle\Translation\Handlers\BidirectionalManyToOneHandler;
use TMI\TranslationBundle\Utils\AttributeHelper;

final class BidirectionalManyToOneHandlerTest extends TestCase
{
    private AttributeHelper $attributeHelper;
    private EntityManagerInterface $em;
    private PropertyAccessor $propertyAccessor;
    private EntityTranslatorInterface $translator;

    public function setUp(): void
    {
        $this->attributeHelper  = $this->createMock(AttributeHelper::class);
        $this->em               = $this->createMock(EntityManagerInterface::class);
        $this->propertyAccessor = new PropertyAccessor();

        $this->translator = new class implements EntityTranslatorInterface {
            public function translate(TranslatableInterface $entity, string $locale): TranslatableInterface
            {
                $clone = clone $entity;
                $clone->setLocale($locale);
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
    }

    private function createHandler(): BidirectionalManyToOneHandler
    {
        return new BidirectionalManyToOneHandler(
            $this->attributeHelper,
            $this->em,
            $this->propertyAccessor,
            $this->translator
        );
    }

    /** ------------------------- Supports Tests -------------------------
     * @throws ReflectionException
     */

    public function testSupportsReturnsFalseWhenNotManyToOne(): void
    {
        $handler = $this->createHandler();
        $entity  = new Scalar();
        $prop    = new ReflectionProperty($entity, 'title');

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        $this->attributeHelper
            ->expects($this->once())
            ->method('isManyToOne')
            ->with($prop)
            ->willReturn(false);

        self::assertFalse($handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsTrueWhenManyToOneWithInversedBy(): void
    {
        $handler = $this->createHandler();

        // Inline entity with inversedBy
        $entity = new class {
            #[ManyToOne(targetEntity: Scalar::class, inversedBy: 'children')]
            private ?Scalar $withInverse = null;
        };

        $prop = new ReflectionProperty($entity, 'withInverse');

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        $this->attributeHelper
            ->expects($this->once())
            ->method('isManyToOne')
            ->with($prop)
            ->willReturn(true);

        self::assertTrue($handler->supports($args));
    }

    /** ------------------------- Shared / Empty Tests -------------------------
     * @throws ReflectionException
     */

    public function testHandleSharedAmongstTranslationsThrows(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableManyToOne();
        $prop    = new ReflectionProperty($entity, 'shared');

        $args = new TranslationArgs($entity);
        $args->setProperty($prop);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessageMatches('/::shared is a Bidirectional ManyToOne/');

        $handler->handleSharedAmongstTranslations($args);
    }

    public function testHandleEmptyOnTranslateReturnsNull(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableManyToOne();
        $args    = new TranslationArgs($entity);

        self::assertNull($handler->handleEmptyOnTranslate($args));
    }

    /** ------------------------- Translate Tests -------------------------
     * @throws ReflectionException
     */

    public function testTranslateWithAssociationMapping(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableManyToOne();

        $metadata = new ClassMetadata(TranslatableManyToOne::class);
        $metadata->associationMappings = [
            'simple' => ['fieldName' => 'simple']
        ];

        $this->em->method('getClassMetadata')
            ->with(TranslatableManyToOne::class)
            ->willReturn($metadata);

        $prop = new ReflectionProperty($entity, 'simple');
        $args = new TranslationArgs($entity, 'en_US', 'it_IT');
        $args->setProperty($prop);
        $scalar = new Scalar();
        $args->setTranslatedParent($scalar);

        $result = $handler->translate($args);

        self::assertInstanceOf(TranslatableManyToOne::class, $result);
        self::assertNotSame($entity, $result);
        self::assertSame('it_IT', $result->getLocale());
        self::assertSame($scalar, $result->getSimple());
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateDelegatesToTranslatorIfNoMapping(): void
    {
        $handler = $this->createHandler();
        $entity  = new TranslatableManyToOne();

        $metadata = new ClassMetadata(TranslatableManyToOne::class);
        $metadata->associationMappings = []; // kein Mapping

        $this->em->method('getClassMetadata')
            ->with(TranslatableManyToOne::class)
            ->willReturn($metadata);

        $prop = new ReflectionProperty($entity, 'empty');
        $args = new TranslationArgs($entity, 'en_US', 'fr_FR');
        $args->setProperty($prop);

        $result = $handler->translate($args);

        self::assertInstanceOf(TranslatableManyToOne::class, $result);
        self::assertNotSame($entity, $result);
        self::assertSame('fr_FR', $result->getLocale());
    }
}
