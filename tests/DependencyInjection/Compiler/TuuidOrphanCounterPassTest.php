<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\DependencyInjection\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Tmi\TranslationBundle\DependencyInjection\Compiler\TuuidOrphanCounterPass;
use Tmi\TranslationBundle\Doctrine\Root\RootCheckAggregator;

#[CoversClass(TuuidOrphanCounterPass::class)]
final class TuuidOrphanCounterPassTest extends TestCase
{
    public function testReturnsWhenTheAggregatorIsNotDefined(): void
    {
        $container = new ContainerBuilder();
        $container->register('counter', \stdClass::class)->addTag(TuuidOrphanCounterPass::TAG);

        new TuuidOrphanCounterPass()->process($container);

        self::assertFalse($container->has(RootCheckAggregator::class));
    }

    public function testAddsEveryTaggedCounterToTheAggregator(): void
    {
        $container = new ContainerBuilder();
        $container->register(RootCheckAggregator::class, RootCheckAggregator::class);
        $container->register('counter.photos', \stdClass::class)->addTag(TuuidOrphanCounterPass::TAG);
        $container->register('counter.reviews', \stdClass::class)->addTag(TuuidOrphanCounterPass::TAG);

        new TuuidOrphanCounterPass()->process($container);

        $calls = $container->getDefinition(RootCheckAggregator::class)->getMethodCalls();

        self::assertCount(2, $calls);

        /** @var array{0: string, 1: array<int, mixed>} $first */
        $first = $calls[0];
        /** @var array{0: string, 1: array<int, mixed>} $second */
        $second = $calls[1];

        self::assertSame('addCounter', $first[0]);
        self::assertEquals([new Reference('counter.photos')], $first[1]);
        self::assertEquals([new Reference('counter.reviews')], $second[1]);
    }
}
