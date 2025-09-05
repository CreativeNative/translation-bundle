<?php

declare(strict_types=1);

namespace TMI\TranslationBundle\Test\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\Handlers\BidirectionalOneToOneHandler;
use TMI\TranslationBundle\Utils\AttributeHelper;

#[\PHPUnit\Framework\Attributes\CoversClass(\TMI\TranslationBundle\Translation\Handlers\BidirectionalOneToOneHandler::class)]
final class BidirectionalOneToOneHandlerTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testTranslateSetsParentAssociation(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getAssociationMappings')->willReturn([
            ['inversedBy' => 'child', 'fieldName' => 'parent'],
        ]);
        $em->method('getClassMetadata')->willReturn($metadata);
        $propertyAccessor = new PropertyAccessor();
        $helper = $this->createMock(AttributeHelper::class);
        $helper->method('isOneToOne')->willReturn(true);
        $handler = new BidirectionalOneToOneHandler($em, $propertyAccessor, $helper);
        $entity = new ParentEntity();
        $prop = new ReflectionProperty($entity::class, 'child');
        $args = new TranslationArgs($entity, 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent(new ParentEntity());
        $result = $handler->translate($args);
        self::assertInstanceOf(ParentEntity::class, $result);
        self::assertNotSame($entity, $result, 'Handler should clone entity, not reuse same instance');
        self::assertSame('de', $result->getLocale(), 'Locale should be updated to target locale');
        self::assertSame($args->getTranslatedParent(), $result->parent, 'Parent association should be set');
    }
}

final class ParentEntity
{
    public ?self $parent = null;
// für parent association
    public ?self $child = null;
// für handler-PropertyAccessor
    private string $locale = 'en';

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }
}
