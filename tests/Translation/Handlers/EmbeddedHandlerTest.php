<?php

declare(strict_types=1);

namespace TMI\TranslationBundle\Test\Translation\Handlers;

use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\Handlers\EmbeddedHandler;
use TMI\TranslationBundle\Translation\Handlers\DoctrineObjectHandler;
use TMI\TranslationBundle\Translation\EntityTranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * @covers \TMI\TranslationBundle\Translation\Handlers\EmbeddedHandler
 */
final class EmbeddedHandlerTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testSupportsDelegatesToAttributeHelper(): void
    {
        $attributeHelper = $this->createMock(AttributeHelper::class);
        $attributeHelper->method('isEmbedded')->willReturn(true);
// create dependency mocks for DoctrineObjectHandler constructor:
        $em = $this->createMock(EntityManagerInterface::class);
        $translator = $this->createMock(EntityTranslatorInterface::class);
// Provide an accessor mock (DoctrineObjectHandler expects a PropertyAccessorInterface as 3rd arg)
        $accessor = $this->createMock(PropertyAccessorInterface::class);
// Construct the real DoctrineObjectHandler (final -> cannot be mocked)
        $doctrineHandler = new DoctrineObjectHandler($em, $translator, $accessor);
// Now construct EmbeddedHandler that delegates to DoctrineObjectHandler
        $embedded = new EmbeddedHandler($attributeHelper, $doctrineHandler);
        $obj = new class {
            public ?string $embedded = null;
        };
// property reflection representing an embedded property
        $prop = new ReflectionProperty($obj::class, 'embedded');
        $args = new TranslationArgs(null, 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($obj);
        self::assertTrue($embedded->supports($args));
    }

    public function testTranslateReturnsCloneAndDelegatesHandlers(): void
    {
        $attributeHelper = $this->createMock(AttributeHelper::class);
        $attributeHelper->method('isEmbedded')->willReturn(true);
        $em = $this->createMock(EntityManagerInterface::class);
        $translator = $this->createMock(EntityTranslatorInterface::class);
        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $doctrineHandler = new DoctrineObjectHandler($em, $translator, $accessor);
        $embedded = new EmbeddedHandler($attributeHelper, $doctrineHandler);
        $entity = new class {
            public ?string $prop = 'x';
        };
        $args = new TranslationArgs($entity, 'en', 'de');
        $result = $embedded->translate($args);
        self::assertNotSame($entity, $result, 'translate should return a clone');
    }

    public function testHandleSharedAmongstTranslationsDelegatesToObjectHandler(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
// real DoctrineObjectHandler is final; we create a real instance but with a translator stub
        $translator = new class implements EntityTranslatorInterface {
            public function translate(TranslatableInterface $entity, string $locale): TranslatableInterface
            {
                return $entity;
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
        $objectHandler = new DoctrineObjectHandler($em, $translator);
        $attributeHelper = $this->createMock(AttributeHelper::class);
        $embedded = new EmbeddedHandler($attributeHelper, $objectHandler);
        $data = new class { public string $foo = 'bar';
        };
        $args = new TranslationArgs($data, 'en', 'de');
        $result = $embedded->handleSharedAmongstTranslations($args);
        $this->assertSame($data, $result, 'EmbeddedHandler should delegate to 
        DoctrineObjectHandler::handleSharedAmongstTranslations (returns same data)');
    }
}
