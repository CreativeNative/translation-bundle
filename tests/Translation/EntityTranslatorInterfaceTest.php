<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation;

use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;

final class EntityTranslatorInterfaceTest extends TestCase
{
    public function testInterfaceMethodsExist(): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);

        $methods = [
            'translate',
            'processTranslation',
            'afterLoad',
            'beforePersist',
            'beforeUpdate',
            'beforeRemove',
        ];

        foreach ($methods as $method) {
            self::assertTrue(
                $reflection->hasMethod($method),
                sprintf('Method %s should exist in EntityTranslatorInterface', $method),
            );
        }
    }

    public function testAfterLoadMethodSignature(): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);
        $method     = $reflection->getMethod('afterLoad');
        $parameters = $method->getParameters();

        self::assertCount(1, $parameters);
        self::assertSame('entity', $parameters[0]->getName());

        $type = $parameters[0]->getType();
        self::assertNotNull($type);
        self::assertEquals(TranslatableInterface::class, $type->getName());
    }

    public function testBeforePersistMethodSignature(): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);
        $method     = $reflection->getMethod('beforePersist');
        $parameters = $method->getParameters();

        self::assertCount(1, $parameters);
        self::assertSame('entity', $parameters[0]->getName());

        $entityType = $parameters[0]->getType();
        self::assertNotNull($entityType);
        self::assertEquals(TranslatableInterface::class, $entityType->getName());
    }

    public function testBeforeUpdateMethodSignature(): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);
        $method     = $reflection->getMethod('beforeUpdate');
        $parameters = $method->getParameters();

        self::assertCount(1, $parameters);
        self::assertSame('entity', $parameters[0]->getName());

        $entityType = $parameters[0]->getType();
        self::assertNotNull($entityType);
        self::assertEquals(TranslatableInterface::class, $entityType->getName());
    }

    public function testBeforeRemoveMethodSignature(): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);
        $method     = $reflection->getMethod('beforeRemove');
        $parameters = $method->getParameters();

        self::assertCount(1, $parameters);
        self::assertSame('entity', $parameters[0]->getName());

        $entityType = $parameters[0]->getType();
        self::assertNotNull($entityType);
        self::assertEquals(TranslatableInterface::class, $entityType->getName());
    }
}
