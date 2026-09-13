<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Tmi\TranslationBundle\Doctrine\Root\RootCheckAggregator;

/**
 * Collects the `tmi_translation.tuuid_orphan_counter` services into
 * {@see RootCheckAggregator}, for `tmi:translation:adopt-root --check` -- the literal
 * shape of {@see TranslationHandlerPass}.
 */
final class TuuidOrphanCounterPass implements CompilerPassInterface
{
    public const string TAG = 'tmi_translation.tuuid_orphan_counter';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(RootCheckAggregator::class)) {
            return;
        }

        $aggregator = $container->findDefinition(RootCheckAggregator::class);

        foreach (array_keys($container->findTaggedServiceIds(self::TAG)) as $id) {
            $aggregator->addMethodCall('addCounter', [new Reference($id)]);
        }
    }
}
