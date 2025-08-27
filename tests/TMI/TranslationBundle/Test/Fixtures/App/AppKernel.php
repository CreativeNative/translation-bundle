<?php

namespace TMI\TranslationBundle\Test\Fixtures\App;

use Doctrine;
use Exception;
use Symfony;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;
use TMI;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class AppKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new TMI\TranslationBundle\TmiTranslationBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function configureContainer(ContainerConfigurator $configurator): void
    {
        $parameters = [
            'locale' => 'en',
            'database_path' => $this->getProjectDir().'/tests/build/test.db',
        ];

        foreach ($parameters as $key => $value) {
            $configurator->parameters()->set($key, $value);
        }

        // Framework
        $configurator->extension('framework', [
            'secret' => 'secret',
            'default_locale' => '%locale%',
            'test' => true,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);

        // Doctrine
        $configurator->extension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'path' => '%database_path%',
            ],
            'orm' => [
                'auto_generate_proxy_classes' => true,
                'auto_mapping' => true,
                'mappings' => [
                    'UnitTestEntities' => [
                        'mapping' => true,
                        'type' => 'annotation',
                        'dir' => $this->getProjectDir().'/../AppTestBundle/Entity/',
                        'alias' => 'Entity',
                        'prefix' => 'AppTestBundle\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        // TMI Translation Bundle
        $locales = ['de', 'en', 'it'];
        $configurator->extension('tmi_translation', [
            'locales' => $locales,
            'default_locale' => 'en',
            'disabled_firewalls' => ['admin'],
        ]);

        // Services Defaults
        $services = $configurator->services()
            ->defaults()
            ->autowire()
            ->autoconfigure()
            ->bind('array $locales', $locales);

        $services->bind('array $locales', $locales);
    }


    /**
     * @throws Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
//        $loader->load(__DIR__ . '/config/config.yaml');
    }
}
