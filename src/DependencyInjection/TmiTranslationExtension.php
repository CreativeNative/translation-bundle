<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\DependencyInjection;

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @see http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
final class TmiTranslationExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @throws Exception
     * @param array<array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Set defaults if missing
        $config['default_locale'] ??= 'en';
        $config['locales'] ??= ['de', 'en', 'it'];

        // Set configuration into params
        $rootName = 'tmi_translation';
        $container->setParameter($rootName, $config);
        $this->setConfigAsParameters($container, $config, $rootName);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
    }

    /**
     * Add config keys as parameters.
     * @param array<string, mixed> $params
     */
    private function setConfigAsParameters(ContainerBuilder $container, array $params, string $parent): void
    {
        foreach ($params as $key => $value) {
            $name = $parent . '.' . $key;
            $container->setParameter($name, $value);

            if (is_array($value)) {
                $this->setConfigAsParameters($container, $value, $name);
            }
        }
    }
}
