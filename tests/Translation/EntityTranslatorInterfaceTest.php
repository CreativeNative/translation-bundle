<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation;

use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

final class EntityTranslatorInterfaceTest extends TestCase
{
    public function testInterfaceMethodsExist(): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);

        $methods = [
            'afterLoad',
            'beforePersist',
            'beforeUpdate',
            'beforeRemove',
        ];

        foreach ($methods as $method) {
            self::assertTrue(
                $reflection->hasMethod($method),
                sprintf('Method %s should exist in EntityTranslatorInterface', $method)
            );
        }
    }

    public function testAfterLoadMethodSignature(): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);
        $method = $reflection->getMethod('afterLoad');
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
        $method = $reflection->getMethod('beforePersist');
        $parameters = $method->getParameters();

        self::assertCount(2, $parameters);
        self::assertSame('entity', $parameters[0]->getName());
        self::assertSame('em', $parameters[1]->getName());

        $entityType = $parameters[0]->getType();
        self::assertNotNull($entityType);
        self::assertEquals(TranslatableInterface::class, $entityType->getName());

        $emType = $parameters[1]->getType();
        self::assertNotNull($emType);
        self::assertEquals(EntityManagerInterface::class, $emType->getName());
    }

    public function testBeforeUpdateMethodSignature(): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);
        $method = $reflection->getMethod('beforeUpdate');
        $parameters = $method->getParameters();

        self::assertCount(2, $parameters);
        self::assertSame('entity', $parameters[0]->getName());
        self::assertSame('em', $parameters[1]->getName());

        $entityType = $parameters[0]->getType();
        self::assertNotNull($entityType);
        self::assertEquals(TranslatableInterface::class, $entityType->getName());

        $emType = $parameters[1]->getType();
        self::assertNotNull($emType);
        self::assertEquals(EntityManagerInterface::class, $emType->getName());
    }

    public function testBeforeRemoveMethodSignature(): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);
        $method = $reflection->getMethod('beforeRemove');
        $parameters = $method->getParameters();

        self::assertCount(2, $parameters);
        self::assertSame('entity', $parameters[0]->getName());
        self::assertSame('em', $parameters[1]->getName());

        $entityType = $parameters[0]->getType();
        self::assertNotNull($entityType);
        self::assertEquals(TranslatableInterface::class, $entityType->getName());

        $emType = $parameters[1]->getType();
        self::assertNotNull($emType);
        self::assertEquals(EntityManagerInterface::class, $emType->getName());
    }
}
