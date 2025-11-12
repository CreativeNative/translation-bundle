<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\DependencyInjection;

use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Tmi\TranslationBundle\Doctrine\Type\TuuidType;

final class TmiTranslationExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @param array<array<string, mixed>> $configs
     *
     * @throws \Exception|\Doctrine\DBAL\Exception|TypesException
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        // Set configuration into params
        $rootName = 'tmi_translation';
        $container->setParameter($rootName, $config);
        $this->setConfigAsParameters($container, $config, $rootName);

        // Register Doctrine Type for Tuuid if not already registered
        if (!Type::hasType(TuuidType::NAME)) {
            Type::addType(TuuidType::NAME, TuuidType::class);
        }

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
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
            $container->setParameter($name, $value);

            if (is_array($value)) {
                $this->setConfigAsParameters($container, $value, $name);
            }
        }
    }
}
