<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\DependencyInjection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Tmi\TranslationBundle\Doctrine\Type\TuuidType;
use Tmi\TranslationBundle\Translation\EntityTranslator;

final class TmiTranslationExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @param array<mixed> $configs
     *
     * @throws \Exception|\Doctrine\DBAL\Exception|TypesException
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        /** @var array{locales: list<string>, default_locale: string, disabled_firewalls: list<string>, logging: array{enabled: bool}} $config */
        $config = $this->processConfiguration($configuration, $configs);

        // Set configuration into params
        $rootName = 'tmi_translation';
        $container->setParameter($rootName, $config);
        $this->setConfigAsParameters($container, $config, $rootName);

        // Register Doctrine Type for Tuuid
        if (!Type::hasType(TuuidType::NAME)) {
            // In DBAL >3 you can't easily unregister, so we skip
            // @codeCoverageIgnoreStart
            Type::addType(TuuidType::NAME, TuuidType::class);
            // @codeCoverageIgnoreEnd
        }

        // Safely map 'tuuid' to 'tuuid' for all platforms
        if ($container->has('doctrine.dbal.default_connection')) {
            $connection = $container->get('doctrine.dbal.default_connection');
            if ($connection instanceof Connection) {
                $platform = $connection->getDatabasePlatform();
                $platform->registerDoctrineTypeMapping('tuuid', 'tuuid');
            }
        }

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        // Configure logging for EntityTranslator
        if ($container->has(EntityTranslator::class)) {
            $definition = $container->getDefinition(EntityTranslator::class);

            $loggingEnabled = $config['logging']['enabled'];

            if (true !== $loggingEnabled) {
                // Explicitly disable - don't inject logger even if available
                $definition->setArgument('$logger', null);
            }
            // If enabled (default), let autowiring handle it via services.yaml
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
    }

    /**
     * Add config keys as parameters.
     *
     * @param array<string, mixed> $params
     */
    private function setConfigAsParameters(ContainerBuilder $container, array $params, string $parent): void
    {
        foreach ($params as $key => $value) {
            $name = $parent.'.'.$key;
            /** @var array<mixed>|bool|float|int|string|\UnitEnum|null $value */
            $container->setParameter($name, $value);

            if (\is_array($value)) {
                /** @var array<string, mixed> $value */
                $this->setConfigAsParameters($container, $value, $name);
            }
        }
    }
}
