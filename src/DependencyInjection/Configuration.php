<?php

namespace Tmi\TranslationBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tmi_translation');

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('locales')
                    ->scalarPrototype()->end()
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                ->end()
                ->scalarNode('default_locale')
                 ->defaultValue('%kernel.default_locale%')
                ->end()
                ->arrayNode('disabled_firewalls')
                    ->info('Defines the firewalls where the filter should be disabled (ex: admin)')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
