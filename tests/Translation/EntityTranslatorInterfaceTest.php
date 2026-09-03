<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation;

use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;

final class EntityTranslatorInterfaceTest extends TestCase
{
    public function testInterfaceMethodsExist(): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);

        $methods = [
            'translate',
            'translateAndPersist',
            'getOrTranslate',
            'processTranslation',
        ];

        foreach ($methods as $method) {
            self::assertTrue(
                $reflection->hasMethod($method),
                sprintf('Method %s should exist in EntityTranslatorInterface', $method),
            );
        }
    }

    /**
     * The four Doctrine lifecycle hooks (afterLoad, beforePersist, beforeUpdate,
     * beforeRemove) were removed in v4.0 (#19): TranslatableEventSubscriber
     * normalises an entity's locale in prePersist/postLoad before any hook ran,
     * so translate($e, $e->getLocale()) always hit the identity return -- the
     * hooks never translated anything, on any flush. Anyone who relied on them
     * as an extension point hooks into Doctrine's own lifecycle events or
     * PreTranslateEvent/PostTranslateEvent instead.
     */
    public function testLifecycleHooksWereRemoved(): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);

        foreach (['afterLoad', 'beforePersist', 'beforeUpdate', 'beforeRemove'] as $method) {
            self::assertFalse(
                $reflection->hasMethod($method),
                sprintf('Method %s should no longer exist on EntityTranslatorInterface', $method),
            );
        }
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateMethodSignature(): void
    {
        $this->assertFirstParameterIsTranslatable('translate', 2);
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateAndPersistMethodSignature(): void
    {
        $this->assertFirstParameterIsTranslatable('translateAndPersist', 2);
    }

    /**
     * @throws \ReflectionException
     */
    public function testGetOrTranslateMethodSignature(): void
    {
        $this->assertFirstParameterIsTranslatable('getOrTranslate', 2);
    }

    /**
     * @throws \ReflectionException
     */
    public function testProcessTranslationMethodSignature(): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);
        $method     = $reflection->getMethod('processTranslation');
        $parameters = $method->getParameters();

        self::assertCount(1, $parameters);
        $param = $parameters[0];
        self::assertNotNull($param->getType(), sprintf('Parameter %s::$%s should have a type', EntityTranslatorInterface::class, $param->getName()));

        $type = $param->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame(TranslationArgs::class, $type->getName());
    }

    /**
     * Helper to assert a method's parameter count and that its first
     * parameter is typed TranslatableInterface.
     *
     * @throws \ReflectionException
     */
    private function assertFirstParameterIsTranslatable(string $methodName, int $expectedParameterCount): void
    {
        $reflection = new \ReflectionClass(EntityTranslatorInterface::class);
        $method     = $reflection->getMethod($methodName);
        $parameters = $method->getParameters();

        self::assertCount($expectedParameterCount, $parameters);
        $param = $parameters[0];
        self::assertNotNull($param->getType(), sprintf('Parameter %s::$%s should have a type', EntityTranslatorInterface::class, $param->getName()));

        $type = $param->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame(TranslatableInterface::class, $type->getName());
    }
}
