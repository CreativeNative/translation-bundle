<?php

namespace TMI\TranslationBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TranslationHandlerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('tmi_translation.translation.entity_translator')) {
            return;
        }

        $definition = $container->findDefinition('tmi_translation.translation.entity_translator');

        // find all service IDs with the app.mail_transport tag
        $taggedServices = $container->findTaggedServiceIds('tmi_translation.translation_handler');

        foreach ($taggedServices as $id => $tags) {
            // add the transport service to the ChainTransport service
            $definition->addMethodCall('addTranslationHandler', [new Reference($id)]);
        }
    }
}
