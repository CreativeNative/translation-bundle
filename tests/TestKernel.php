<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Tmi\TranslationBundle\Doctrine\EventSubscriber\TranslatableEventSubscriber;
use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;
use Tmi\TranslationBundle\TmiTranslationBundle;

final class TestKernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new TmiTranslationBundle(),
        ];
    }

    public function configureContainer(ContainerConfigurator $container): void
    {
        $locales = ['en_US', 'de_DE', 'it_IT'];

        $container->extension('framework', [
            'secret' => 'test_secret',
            'test' => true,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);

        $container->extension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'memory' => true,
                'charset' => 'utf8',
            ],
            'orm' => [
                'auto_generate_proxy_classes' => true,
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'auto_mapping' => true,
                'mappings' => [
                    'TestBundle' => [
                        'type' => 'attribute',
                        'dir' => '%kernel.project_dir%/tests/Fixtures/Entity',
                        'prefix' => 'Tmi\TranslationBundle\Fixtures\Entity',
                        'alias' => 'TestBundle',
                    ],
                ],
                'filters' => [
                    'tmi_translation_locale_filter' => [
                        'class' => LocaleFilter::class,
                        'enabled' => true,
                    ],
                ],
            ],
        ]);

        $container->extension('tmi_translation', [
            'locales' => $locales,
            'default_locale' => 'en_US',
            'disabled_firewalls' => ['admin'],
        ]);

        $container->services()
            ->defaults()
            ->autowire()
            ->autoconfigure()
            ->bind('array $locales', $locales);

        $container->services()
            ->set(TranslatableEventSubscriber::class)
            ->public()
            ->tag('doctrine.event_subscriber');
    }
}
