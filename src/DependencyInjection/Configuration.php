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
                ->booleanNode('strict_orphan_check')
                    ->defaultNull()
                    ->info('Throw when an entity is flushed in a non-default locale without a Tuuid shared by any other locale variant. Null (default) = auto: enabled when kernel.debug is true.')
                ->end()
                ->booleanNode('unique_locale_variants')
                    ->defaultFalse()
                    ->info('Promote the auto-injected (tuuid, locale) index to a UNIQUE constraint. Enable only once existing data is free of duplicate locale rows (see tmi:translation:doctor).')
                ->end()
                ->booleanNode('cascade_remove_locale_variants')
                    ->defaultFalse()
                    ->info('When true, $em->remove() on a translatable entity also removes every sibling locale variant sharing its Tuuid. Use TranslatableRemover::removeSingleLocaleVariant() to delete one variant only while this is enabled.')
                ->end()
                ->booleanNode('propagate_shared_on_flush')
                    ->defaultFalse()
                    ->info('When true, a change to a #[SharedAmongstTranslations] property on any locale variant is copied onto every other variant of the same Tuuid inside the same flush() (SharedValuePropagationListener). Off by default in 4.x; announced as the 5.0 default. Enable once tmi:translation:sync-shared --check reports zero drift, and after removing the attribute from every property your application diverges per locale on purpose.')
                ->end()
                ->booleanNode('strict_discovery')
                    ->defaultFalse()
                    ->info('Throw at compile time when AttributeValidationPass discovers zero TranslatableInterface entities under the configured Doctrine attribute mapping directories, instead of only logging it. Off by default because an empty result can be legitimate (no translatable entities yet); turn it on once you have at least one, to catch doctrine-bundle silently changing the shape of its attribute metadata driver definitions.')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
