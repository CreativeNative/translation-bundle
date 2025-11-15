<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\DoctrineObjectHandler;
use Tmi\TranslationBundle\Translation\Handlers\EmbeddedHandler;

#[\PHPUnit\Framework\Attributes\CoversClass(EmbeddedHandler::class)]
final class EmbeddedHandlerTest extends UnitTestCase
{
    private EmbeddedHandler $embeddedHandler;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        // Create the real DoctrineObjectHandler (final -> cannot be mocked)
        $doctrineHandler = new DoctrineObjectHandler(
            $this->entityManager,
            $this->translator,
            $this->propertyAccessor,
        );

        // Create EmbeddedHandler that delegates to DoctrineObjectHandler
        $this->embeddedHandler = new EmbeddedHandler(
            $this->attributeHelper,
        );
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsDelegatesToAttributeHelper(): void
    {
        $this->attributeHelper->expects($this->once())
            ->method('isEmbedded')
            ->willReturn(true);

        $obj = new class {
            public string|null $embedded = null;
        };

        // property reflection representing an embedded property
        $prop = new \ReflectionProperty($obj::class, 'embedded');
        $args = new TranslationArgs(null, 'en_US', 'de_DE')
            ->setProperty($prop)
            ->setTranslatedParent($obj);

        self::assertTrue($this->embeddedHandler->supports($args));
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateReturnsCloneAndDelegatesHandlers(): void
    {
        $entity = new class {
            public string|null $prop = 'x';
        };

        $args   = new TranslationArgs($entity, 'en_US', 'de_DE');
        $result = $this->embeddedHandler->translate($args);

        self::assertNotSame($entity, $result, 'translate should return a clone');
    }

    /**
     * @throws \ReflectionException
     */
    public function testHandleSharedAmongstTranslationsDelegatesToObjectHandler(): void
    {
        $data = new class {
            public string $foo = 'bar';
        };

        $args   = new TranslationArgs($data, 'en_US', 'de_DE');
        $result = $this->embeddedHandler->handleSharedAmongstTranslations($args);

        $this->assertEquals(
            $data,
            $result,
            'EmbeddedHandler should delegate to DoctrineObjectHandler::handleSharedAmongstTranslations (returns same data)',
        );
    }
}
