<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tmi\TranslationBundle\DependencyInjection\Compiler\AttributeValidationPass;
use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\IntegrationTestCase;

/**
 * Discovery tripwire for AttributeValidationPass::discoverTranslatableClasses().
 *
 * That method finds its target entities by pattern-matching container service
 * ids for "attribute_metadata_driver" and reading the first constructor
 * argument as a list of mapping directories - the only way to reach Doctrine
 * mapping configuration at compile time, before any EntityManager exists.
 * AttributeValidationPassTest hand-builds that service definition for every
 * other case, which proves the reading logic but never proves doctrine-bundle
 * still *produces* a service in that shape.
 *
 * This test instead builds a ContainerBuilder and loads the real doctrine-bundle
 * DoctrineExtension against it, configured the same way tests/TestKernel.php
 * configures doctrine. If doctrine-bundle ever renames or reshapes the
 * attribute metadata driver service, discovery silently finds nothing and this
 * test - not a hand-built one - is what catches it.
 */
final class AttributeValidationPassDoctrineDiscoveryTest extends IntegrationTestCase
{
    public function testDiscoverTranslatableClassesFindsFixtureEntityThroughRealDoctrineBundleExtension(): void
    {
        $containerBuilder = $this->containerBuilderFromBootedKernelParameters();

        $extension = new DoctrineExtension();
        $extension->load([$this->doctrineConfig($containerBuilder)], $containerBuilder);

        // The real extension, not a hand-built definition, produced this.
        self::assertTrue($containerBuilder->has('doctrine.orm.entity_manager'));

        $pass   = new AttributeValidationPass();
        $method = new \ReflectionMethod($pass, 'discoverTranslatableClasses');

        /** @var array<\ReflectionClass<object>> $discovered */
        $discovered = $method->invoke($pass, $containerBuilder);

        $discoveredNames = array_map(
            static fn (\ReflectionClass $class): string => $class->getName(),
            $discovered,
        );

        self::assertContains(Scalar::class, $discoveredNames);
    }

    /**
     * The exact 'doctrine' extension configuration tests/TestKernel.php uses,
     * with the %kernel.project_dir%/... placeholder resolved to a literal path
     * - MergeExtensionConfigurationPass resolves such placeholders before
     * calling Extension::load() during a real kernel boot; calling load()
     * directly here bypasses that, so the literal path is supplied instead.
     *
     * @return array<string, mixed>
     */
    private function doctrineConfig(ContainerBuilder $containerBuilder): array
    {
        $projectDir = $containerBuilder->getParameter('kernel.project_dir');
        self::assertIsString($projectDir, 'kernel.project_dir parameter must be a string');

        return [
            'dbal' => [
                'driver'  => 'pdo_sqlite',
                'memory'  => true,
                'charset' => 'utf8',
            ],
            'orm' => [
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'auto_mapping'    => true,
                'mappings'        => [
                    'TestBundle' => [
                        'type'   => 'attribute',
                        'dir'    => $projectDir.'/tests/Fixtures/Entity',
                        'prefix' => 'Tmi\TranslationBundle\Fixtures\Entity',
                        'alias'  => 'TestBundle',
                    ],
                ],
                'filters' => [
                    'tmi_translation_locale_filter' => [
                        'class'   => LocaleFilter::class,
                        'enabled' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * A fresh ContainerBuilder seeded with the already-booted kernel's
     * resolved parameters (kernel.project_dir, kernel.debug, kernel.bundles,
     * ...), so the real DoctrineExtension has everything it expects without
     * booting a second kernel just for this one compiler-pass test.
     */
    private function containerBuilderFromBootedKernelParameters(): ContainerBuilder
    {
        $containerBuilder = new ContainerBuilder();
        $container        = self::getContainer();

        foreach ($container->getParameterBag()->all() as $key => $value) {
            if (is_scalar($value) || is_array($value) || null === $value || $value instanceof \UnitEnum) {
                $containerBuilder->setParameter($key, $value);
            }
        }

        return $containerBuilder;
    }
}
