<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\DependencyInjection\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tmi\TranslationBundle\DependencyInjection\Compiler\AttributeValidationPass;
use Tmi\TranslationBundle\DependencyInjection\Compiler\RootAdopterPass;
use Tmi\TranslationBundle\Doctrine\Root\RootAdopterRegistry;
use Tmi\TranslationBundle\Exception\TranslationRootContractException;
use Tmi\TranslationBundle\Fixtures\Validation\Root\StiDedupe\AbstractStiRows;
use Tmi\TranslationBundle\Fixtures\Validation\Root\StiDedupe\StiLeafOne;
use Tmi\TranslationBundle\Fixtures\Validation\Root\StiDedupe\StiLeafTwo;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Valid\Phase1Entity;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Valid\Phase2Entity;

/**
 * Collects the root adopters and cross-checks them against the classes that declare
 * a root reference -- at compile time, from the tag's `class` attribute alone.
 */
#[CoversClass(RootAdopterPass::class)]
final class RootAdopterPassTest extends TestCase
{
    public function testReturnsWhenTheRegistryIsNotDefined(): void
    {
        $container = new ContainerBuilder();
        $container->register('adopter', \stdClass::class)->addTag(RootAdopterPass::TAG);

        new RootAdopterPass()->process($container);

        self::assertFalse($container->has(RootAdopterRegistry::class));
    }

    public function testRegistersEveryTaggedAdopterWithItsClass(): void
    {
        $container = $this->container([Phase1Entity::class, Phase2Entity::class]);
        $container->register('adopter.one', \stdClass::class)->addTag(RootAdopterPass::TAG, ['class' => Phase1Entity::class]);
        $container->register('adopter.two', \stdClass::class)->addTag(RootAdopterPass::TAG, ['class' => Phase2Entity::class]);

        new RootAdopterPass()->process($container);

        $calls = $container->getDefinition(RootAdopterRegistry::class)->getMethodCalls();

        self::assertCount(2, $calls);

        /** @var array{0: string, 1: array<int, mixed>} $first */
        $first = $calls[0];
        /** @var array{0: string, 1: array<int, mixed>} $second */
        $second = $calls[1];

        self::assertSame('addAdopter', $first[0]);
        self::assertEquals([new Reference('adopter.one'), Phase1Entity::class], $first[1]);
        self::assertEquals([new Reference('adopter.two'), Phase2Entity::class], $second[1]);
    }

    public function testATagWithoutAClassAttributeIsRefused(): void
    {
        $container = $this->container([]);
        $container->register('adopter.anonymous', \stdClass::class)->addTag(RootAdopterPass::TAG);

        self::expectException(TranslationRootContractException::class);
        self::expectExceptionMessage('"adopter.anonymous" is tagged `tmi_translation.root_adopter` without the required `class` attribute');

        new RootAdopterPass()->process($container);
    }

    public function testARootClassWithoutAnAdopterIsRefused(): void
    {
        $container = $this->container([Phase1Entity::class]);

        $message = $this->processExpectingFailure($container);

        self::assertStringContainsString('1 error(s)', $message);
        self::assertStringContainsString(Phase1Entity::class.' declares a root reference but no service tagged', $message);
    }

    public function testARootClassServedByTwoAdoptersIsRefused(): void
    {
        $container = $this->container([Phase1Entity::class]);
        $container->register('adopter.one', \stdClass::class)->addTag(RootAdopterPass::TAG, ['class' => Phase1Entity::class]);
        $container->register('adopter.two', \stdClass::class)->addTag(RootAdopterPass::TAG, ['class' => Phase1Entity::class]);

        $message = $this->processExpectingFailure($container);

        self::assertStringContainsString('is served by 2 root adopters (adopter.one, adopter.two)', $message);
    }

    public function testAnAdopterForAClassWithoutARootReferenceIsRefused(): void
    {
        $container = $this->container([Phase1Entity::class]);
        $container->register('adopter.one', \stdClass::class)->addTag(RootAdopterPass::TAG, ['class' => Phase1Entity::class]);
        $container->register('adopter.stray', \stdClass::class)->addTag(RootAdopterPass::TAG, ['class' => \stdClass::class]);

        $message = $this->processExpectingFailure($container);

        self::assertStringContainsString('1 error(s)', $message);
        self::assertStringContainsString('service "adopter.stray" is tagged `tmi_translation.root_adopter` for stdClass, but no concrete translatable class under it declares a root reference', $message);
    }

    /**
     * The application registers ONE adopter for the hierarchy root; the concrete
     * SINGLE_TABLE leaves the validation pass discovered resolve to it by ancestry.
     */
    public function testAnAdopterForAnAbstractAncestorServesEveryConcreteLeaf(): void
    {
        $container = $this->container([StiLeafOne::class, StiLeafTwo::class]);
        $container->register('adopter.sti', \stdClass::class)->addTag(RootAdopterPass::TAG, ['class' => AbstractStiRows::class]);

        new RootAdopterPass()->process($container);

        self::assertCount(1, $container->getDefinition(RootAdopterRegistry::class)->getMethodCalls());
    }

    public function testWithoutDoctrineTheRegistryIsFilledButNothingIsCrossChecked(): void
    {
        $container = new ContainerBuilder();
        $container->register(RootAdopterRegistry::class, RootAdopterRegistry::class);
        $container->register('adopter.stray', \stdClass::class)->addTag(RootAdopterPass::TAG, ['class' => \stdClass::class]);

        new RootAdopterPass()->process($container);

        self::assertCount(1, $container->getDefinition(RootAdopterRegistry::class)->getMethodCalls());
    }

    public function testWithDoctrineButWithoutTheParameterNoRootClassIsAssumed(): void
    {
        $container = new ContainerBuilder();
        $container->register('doctrine.orm.entity_manager', \stdClass::class);
        $container->register(RootAdopterRegistry::class, RootAdopterRegistry::class);

        new RootAdopterPass()->process($container);

        self::assertCount(0, $container->getDefinition(RootAdopterRegistry::class)->getMethodCalls());
    }

    private function processExpectingFailure(ContainerBuilder $container): string
    {
        try {
            new RootAdopterPass()->process($container);
        } catch (\LogicException $e) {
            return $e->getMessage();
        }

        self::fail('Expected the pass to refuse the container.');
    }

    /**
     * @param list<class-string> $rootClasses what AttributeValidationPass would have computed
     */
    private function container(array $rootClasses): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('doctrine.orm.entity_manager', \stdClass::class);
        $container->setDefinition(RootAdopterRegistry::class, new Definition(RootAdopterRegistry::class));
        $container->setParameter(AttributeValidationPass::ROOT_CLASSES_PARAMETER, $rootClasses);

        return $container;
    }
}
