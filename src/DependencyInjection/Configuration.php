<?php

declare(strict_types=1);

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
                ->scalarNode('default_locale')
                    ->defaultValue('%kernel.default_locale%')
                ->end()
                ->arrayNode('disabled_firewalls')
                    ->info('Firewalls where the locale filter is disabled (e.g., admin)')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->booleanNode('enable_logging')
                    ->defaultFalse()
                    ->info('Enable debug logging when PSR-3 logger is available (opt-in)')
                ->end()
                ->booleanNode('copy_source')
                    ->defaultFalse()
                    ->info('When false, new translations start empty with type-safe defaults. When true, translations clone source content (v1.x behavior).')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
