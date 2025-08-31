<?php

namespace TMI\TranslationBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('tmi_translation');
        $rootNode = $builder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('locales')
                    ->prototype('scalar')->end()
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                ->end()
                ->scalarNode('default_locale')
                    ->defaultValue('%kernel.default_locale%')
                ->end()
                ->arrayNode('disabled_firewalls')->info('Defines the firewalls where the filter should be disabled (ex: admin)')
                    ->prototype('scalar')->end()->defaultValue([])
                ->end()
            ->end()
        ;

        return $builder;
    }
}
