<?php
declare(strict_types=1);

namespace TMI\TranslationBundle\Test\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\Mapping\MappingException;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslator;
use TMI\TranslationBundle\Translation\Handlers\DoctrineObjectHandler;
use TMI\TranslationBundle\Utils\AttributeHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \TMI\TranslationBundle\Translation\Handlers\DoctrineObjectHandler
 */
final class DoctrineObjectHandlerTest extends TestCase
{
    private function newTranslator(): EntityTranslator
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $attributeHelper = $this->createMock(AttributeHelper::class);

        return new EntityTranslator(
            defaultLocale: 'en',
            locales: ['en', 'de'],
            eventDispatcher: $dispatcher,
            attributeHelper: $attributeHelper
        );
    }

    /**
     * @throws MappingException
     */
    public function testSupportsWithManagedEntity(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $mf = $this->createMock(ClassMetadataFactory::class);

        $em->method('getMetadataFactory')->willReturn($mf);
        $mf->method('isTransient')->willReturn(false);

        $translator = $this->newTranslator();
        $handler = new DoctrineObjectHandler($em, $translator);

        $args = new TranslationArgs(new \stdClass(), 'en', 'de');
        self::assertTrue($handler->supports($args));
    }

    /**
     * @throws MappingException
     */
    public function testSupportsWithTransientEntity(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $mf = $this->createMock(ClassMetadataFactory::class);

        $em->method('getMetadataFactory')->willReturn($mf);
        $mf->method('isTransient')->willReturn(true);

        $translator = $this->newTranslator();
        $handler = new DoctrineObjectHandler($em, $translator);

        $args = new TranslationArgs(new \stdClass(), 'en', 'de');
        self::assertFalse($handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateClonesEntity(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $mf = $this->createMock(ClassMetadataFactory::class);

        $em->method('getMetadataFactory')->willReturn($mf);
        $mf->method('isTransient')->willReturn(false);

        $translator = $this->newTranslator();
        $handler = new DoctrineObjectHandler($em, $translator);

        $entity = new class {
            public string $child = 'foo';
        };

        $args = new TranslationArgs($entity, 'en', 'de');
        $result = $handler->translate($args);

        self::assertNotSame($entity, $result, 'Entity should be cloned, not the same instance');
        self::assertSame('foo', $result->child, 'Cloned entity should preserve property values');
    }
}
