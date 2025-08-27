<?php

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class TestKernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function configureContainer(ContainerConfigurator $configurator): void
    {
        $locales = ['de', 'en', 'it'];

        $configurator->extension('framework', [
            'secret' => 'test_secret',
            'test' => true,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);

        $configurator->extension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'path' => $this->getProjectDir() . '/../../build/test.db',
            ],
            'orm' => ['auto_generate_proxy_classes' => true],
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
