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
        // Detect removed v1.x config keys and provide migration guidance
        foreach ($configs as $subConfig) {
            if (\is_array($subConfig) && isset($subConfig['locales'])) {
                throw new \LogicException(
                    'The "tmi_translation.locales" option was removed in v2.0. '
                    .'Configure "framework.enabled_locales" instead.',
                );
            }
            if (\is_array($subConfig) && isset($subConfig['logging'])) {
                throw new \LogicException(
                    'The "tmi_translation.logging" option was removed in v2.0. '
                    .'Use "tmi_translation.enable_logging: true" instead.',
                );
            }
        }

        $configuration = new Configuration();
        /** @var array{default_locale: string, disabled_firewalls: list<string>, enable_logging: bool} $config */
        $config = $this->processConfiguration($configuration, $configs);

        // Read locales from Symfony's framework.enabled_locales
        /** @var list<string> $enabledLocales */
        $enabledLocales = $container->hasParameter('kernel.enabled_locales')
            ? $container->getParameter('kernel.enabled_locales')
            : [];

        if ([] === $enabledLocales) {
            throw new \LogicException(
                'The tmi/translation-bundle requires framework.enabled_locales to be configured. '
                .'Add "enabled_locales" to your framework configuration.',
            );
        }

        // Validate that default_locale is included in enabled_locales
        $defaultLocale = $config['default_locale'];
        if (!\in_array($defaultLocale, $enabledLocales, true)) {
            throw new \LogicException(\sprintf(
                'The default_locale "%s" must be included in framework.enabled_locales [%s].',
                $defaultLocale,
                implode(', ', $enabledLocales),
            ));
        }

        // Set configuration into params
        $rootName = 'tmi_translation';
        $container->setParameter($rootName, $config);
        $this->setConfigAsParameters($container, $config, $rootName);

        // Register Doctrine Type for Tuuid
        if (!Type::hasType(TuuidType::NAME)) {
            Type::addType(TuuidType::NAME, TuuidType::class);
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

            if (!$config['enable_logging']) {
                // Explicitly disable - don't inject logger even if available
                $definition->setArgument('$logger', null);
            }
            // If enabled, let autowiring handle it via services.yaml
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
            assert(is_array($value) || is_scalar($value) || $value instanceof \UnitEnum || null === $value);
            $container->setParameter($name, $value);

            if (\is_array($value)) {
                /** @var array<string, mixed> $value */
                $this->setConfigAsParameters($container, $value, $name);
            }
        }
    }
}
