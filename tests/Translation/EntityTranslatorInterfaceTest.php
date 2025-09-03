<?php

namespace TMI\TranslationBundle\Test\Translation;

use PHPUnit\Framework\TestCase;
use TMI\TranslationBundle\Translation\EntityTranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * @coversDefaultClass \TMI\TranslationBundle\Translation\EntityTranslatorInterface
 */
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
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('Method %s should exist in EntityTranslatorInterface', $method)
            );
        }
    }


    /**
     * @covers ::afterLoad
     */
    public function testAfterLoadMethodSignature(): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);
        $method = $reflection->getMethod('afterLoad');
        $parameters = $method->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('entity', $parameters[0]->getName());

        $type = $parameters[0]->getType();
        $this->assertNotNull($type);
        $this->assertEquals(TranslatableInterface::class, $type->getName());
    }

    /**
     * @covers ::beforePersist
     */
    public function testBeforePersistMethodSignature(): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);
        $method = $reflection->getMethod('beforePersist');
        $parameters = $method->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('entity', $parameters[0]->getName());
        $this->assertEquals('em', $parameters[1]->getName());

        $entityType = $parameters[0]->getType();
        $this->assertNotNull($entityType);
        $this->assertEquals(TranslatableInterface::class, $entityType->getName());

        $emType = $parameters[1]->getType();
        $this->assertNotNull($emType);
        $this->assertEquals(EntityManagerInterface::class, $emType->getName());
    }

    /**
     * @covers ::beforeUpdate
     */
    public function testBeforeUpdateMethodSignature(): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);
        $method = $reflection->getMethod('beforeUpdate');
        $parameters = $method->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('entity', $parameters[0]->getName());
        $this->assertEquals('em', $parameters[1]->getName());

        $entityType = $parameters[0]->getType();
        $this->assertNotNull($entityType);
        $this->assertEquals(TranslatableInterface::class, $entityType->getName());

        $emType = $parameters[1]->getType();
        $this->assertNotNull($emType);
        $this->assertEquals(EntityManagerInterface::class, $emType->getName());
    }

    /**
     * @covers ::beforeRemove
     */
    public function testBeforeRemoveMethodSignature(): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);
        $method = $reflection->getMethod('beforeRemove');
        $parameters = $method->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('entity', $parameters[0]->getName());
        $this->assertEquals('em', $parameters[1]->getName());

        $entityType = $parameters[0]->getType();
        $this->assertNotNull($entityType);
        $this->assertEquals(TranslatableInterface::class, $entityType->getName());

        $emType = $parameters[1]->getType();
        $this->assertNotNull($emType);
        $this->assertEquals(EntityManagerInterface::class, $emType->getName());
    }
}