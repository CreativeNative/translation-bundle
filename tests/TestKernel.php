<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Tmi\TranslationBundle\Doctrine\EventSubscriber\TranslatableEventSubscriber;
use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;
use Tmi\TranslationBundle\Test\Support\QueryCounter;
use Tmi\TranslationBundle\TmiTranslationBundle;
use Tmi\TranslationBundle\Translation\EntityTranslator;

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

        // EntityTranslator (and everything it needs) is private in the bundle's own
        // services.yaml (v4.0) -- correctly so, since a real consuming application
        // anchors it the moment one of ITS OWN autowired services type-hints
        // EntityTranslatorInterface. This test kernel has no such consumer, so
        // without this alias the whole cluster is unreachable from any public
        // service and the compiler prunes it outright; IntegrationTestCase fetches
        // the translator through this public alias rather than the private class id.
        $container->services()
            ->alias('test.entity_translator', EntityTranslator::class)
            ->public();

        // Query-budget test infrastructure: a PSR-3 logger counting the debug
        // messages DBAL's own logging middleware emits, one per executed
        // statement or query. Registering the vendor Middleware class here
        // (rather than the bundle's own config) is what keeps this test-only --
        // doctrine-bundle autoconfigures every Doctrine\DBAL\Driver\Middleware
        // implementation onto the "doctrine.middleware" tag (verified against
        // doctrine-bundle 3.3.1's DoctrineExtension::dbalLoad()), which
        // MiddlewaresPass then attaches to the (only) "default" connection --
        // but only for a definition that is itself autoconfigured. That flag
        // does NOT carry over from the `defaults()` call above: ServicesConfigurator
        // ::defaults() stores it on that specific configurator instance
        // (vendor ServicesConfigurator::$defaults, fresh per `services()`
        // call), so it applies only to registrations chained off the very
        // same `services()` return value, never to a later, separate
        // `$container->services()->set(...)` call such as this one --
        // verified by tracing autoconfigured() through a compiler pass while
        // building this test. Every existing registration below the
        // `defaults()` call happens to not depend on it (TranslatableEventSubscriber
        // resolves its constructor via #[Autowire] attributes, not autowiring;
        // the two aliases need neither), so this went unnoticed until a
        // registration that genuinely needs the "doctrine.middleware"
        // instanceof tag exposed it.
        $container->services()
            ->set(QueryCounter::class)
            ->public();

        $container->services()
            ->set('test.query_counter_middleware', LoggingMiddleware::class)
            ->autoconfigure()
            ->arg('$logger', service(QueryCounter::class));
    }
}
