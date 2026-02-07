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

    /**
     * @throws \ReflectionException
     */
    public function testAfterLoadMethodSignature(): void
    {
        $this->assertParameterType('afterLoad');
    }

    /**
     * @throws \ReflectionException
     */
    public function testBeforePersistMethodSignature(): void
    {
        $this->assertParameterType('beforePersist');
    }

    /**
     * @throws \ReflectionException
     */
    public function testBeforeUpdateMethodSignature(): void
    {
        $this->assertParameterType('beforeUpdate');
    }

    /**
     * @throws \ReflectionException
     */
    public function testBeforeRemoveMethodSignature(): void
    {
        $this->assertParameterType('beforeRemove');
    }

    /**
     * Helper to assert that a method parameter has the expected type.
     *
     * @throws \ReflectionException
     */
    private function assertParameterType(string $methodName): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);
        $method     = $reflection->getMethod($methodName);
        $parameters = $method->getParameters();

        self::assertCount(1, $parameters);
        $param = $parameters[0];
        self::assertNotNull($param->getType(), sprintf('Parameter %s::$%s should have a type', EntityTranslatorInterface::class, $param->getName()));

        $type = $param->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame(TranslatableInterface::class, $type->getName());
    }
}
