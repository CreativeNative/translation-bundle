<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class TranslationHandlerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('tmi_translation.translation.entity_translator')) {
            return;
        }

        $definition = $container->findDefinition('tmi_translation.translation.entity_translator');

        $taggedServices = $container->findTaggedServiceIds('tmi_translation.translation_handler');

        foreach ($taggedServices as $id => $tags) {
            // The chain is first-match-wins, so a custom handler tagged with a priority has
            // to be inserted at that position instead of being appended after the broad
            // built-in handlers, where it would never be reached.
            $attributes = $tags[0]                                         ?? null;
            $priority   = \is_array($attributes) ? $attributes['priority'] ?? null : null;

            $definition->addMethodCall('addTranslationHandler', [
                new Reference($id),
                is_numeric($priority) ? (int) $priority : null,
            ]);
        }
    }
}
