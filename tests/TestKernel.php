<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Tmi\TranslationBundle\Doctrine\EventSubscriber\TranslatableEventSubscriber;
use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;
use Tmi\TranslationBundle\TmiTranslationBundle;

final class TestKernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Typed as the concrete bundles rather than BundleInterface. Symfony 8.1
     * deprecated HttpKernel\Bundle\BundleInterface, but its replacement
     * DependencyInjection\Kernel\BundleInterface is the *wider* parent type,
     * while HttpKernel\KernelInterface::registerBundles() still declares the
     * narrow deprecated one — so naming the replacement here breaks covariance.
     * Listing the concrete bundles satisfies both and names neither.
     *
     * @return list<FrameworkBundle|DoctrineBundle|TmiTranslationBundle>
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
            'secret'          => 'test_secret',
            'test'            => true,
            'session'         => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'enabled_locales' => $locales,
        ]);

        $container->extension('doctrine', [
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
                        'dir'    => '%kernel.project_dir%/tests/Fixtures/Entity',
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
        ]);

        $container->extension('tmi_translation', [
            'default_locale'      => 'en_US',
            'disabled_firewalls'  => ['admin'],
            'copy_source'         => true,
            'strict_orphan_check' => false,
            'strict_discovery'    => true,
        ]);

        // Without monolog, the framework's fallback logger writes to the SAPI
        // log. Under CI's php.ini that surfaces as test output ("Notified
        // event ... to listener ...") and trips failOnRisky — a NullLogger
        // makes test output deterministic across environments.
        $container->services()->set('logger', NullLogger::class);

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
