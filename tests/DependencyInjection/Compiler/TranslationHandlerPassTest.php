<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tmi\TranslationBundle\DependencyInjection\Compiler\TranslationHandlerPass;

final class TranslationHandlerPassTest extends TestCase
{
    public function testProcessReturnsIfTranslatorServiceMissing(): void
    {
        $container = new ContainerBuilder();
        $pass      = new TranslationHandlerPass();

        // The container does NOT have the translator service
        self::assertFalse($container->has('tmi_translation.translation.entity_translator'));

        // process() should execute and immediately return without error
        $pass->process($container);

        // Assert translator service still does not exist
        self::assertFalse($container->has('tmi_translation.translation.entity_translator'));
    }

    public function testProcessAddsTaggedHandlersToTranslator(): void
    {
        $container = new ContainerBuilder();

        // Create a mock translator service definition
        $translatorDefinition = new Definition();
        $container->setDefinition('tmi_translation.translation.entity_translator', $translatorDefinition);

        // Create some tagged services
        $handler1 = new Definition();
        $handler1->addTag('tmi_translation.translation_handler');
        $container->setDefinition('handler.one', $handler1);

        $handler2 = new Definition();
        $handler2->addTag('tmi_translation.translation_handler');
        $container->setDefinition('handler.two', $handler2);

        $pass = new TranslationHandlerPass();
        $pass->process($container);

        $methodCalls = $translatorDefinition->getMethodCalls();

        // Assert that addTranslationHandler was called for each tagged service
        self::assertCount(2, $methodCalls);

        /** @var array{0: string, 1: array<int, mixed>} $call0 */
        $call0 = $methodCalls[0];
        /** @var array{0: string, 1: array<int, mixed>} $call1 */
        $call1 = $methodCalls[1];

        self::assertSame('addTranslationHandler', $call0[0]);
        self::assertInstanceOf(Reference::class, $call0[1][0]);
        self::assertSame('handler.one', (string) $call0[1][0]);

        self::assertSame('addTranslationHandler', $call1[0]);
        self::assertInstanceOf(Reference::class, $call1[1][0]);
        self::assertSame('handler.two', (string) $call1[1][0]);
    }
}
