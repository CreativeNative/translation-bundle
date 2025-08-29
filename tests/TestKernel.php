<?php

namespace TMI\TranslationBundle\Test;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use TMI\TranslationBundle\TMITranslationBundle;

class TestKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new TMITranslationBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $configurator): void
    {
        $locales = ['de', 'en', 'it'];

        $configurator->extension('framework', [
            'secret' => 'test_secret',
            'test' => true,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file']
        ]);

        $configurator->extension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'memory' => true,
                'charset' => 'utf8'
            ],
            'orm' => [
                'auto_generate_proxy_classes' => true,
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'auto_mapping' => true,
                'mappings' => [
                    'TestBundle' => [
                        'type' => 'attribute',
                        'dir' => '%kernel.project_dir%/tests/Fixtures/Entity',
                        'prefix' => 'TMI\TranslationBundle\Fixtures\Entity',
                        'alias' => 'TestBundle',
                    ]
                ]
            ]
        ]);

        $configurator->extension('tmi_translation', [
            'locales' => $locales,
            'default_locale' => 'en',
            'disabled_firewalls' => ['admin'],
        ]);

        $configurator->services()
            ->defaults()
            ->autowire()
            ->autoconfigure()
            ->bind('array $locales', $locales);
    }
}
