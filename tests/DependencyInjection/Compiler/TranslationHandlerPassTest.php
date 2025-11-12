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
        $this->assertFalse($container->has('tmi_translation.translation.entity_translator'));

        // process() should execute and immediately return without error
        $pass->process($container);

        // Assert translator service still does not exist
        $this->assertFalse($container->has('tmi_translation.translation.entity_translator'));
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
        $this->assertCount(2, $methodCalls);

        $this->assertSame('addTranslationHandler', $methodCalls[0][0]);
        $this->assertInstanceOf(Reference::class, $methodCalls[0][1][0]);
        $this->assertSame('handler.one', (string) $methodCalls[0][1][0]);

        $this->assertSame('addTranslationHandler', $methodCalls[1][0]);
        $this->assertInstanceOf(Reference::class, $methodCalls[1][1][0]);
        $this->assertSame('handler.two', (string) $methodCalls[1][1][0]);
    }
}
