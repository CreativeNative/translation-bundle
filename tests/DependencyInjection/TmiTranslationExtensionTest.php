<?php
declare(strict_types=1);


namespace TMI\TranslationBundle\Test\DependencyInjection;

use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TMI\TranslationBundle\DependencyInjection\TmiTranslationExtension;

final class TmiTranslationExtensionTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testLoad(): void
    {
        $container = new ContainerBuilder();
        $extension = new TmiTranslationExtension();

        // Provide all required configuration
        $config = [
            [
                'locales' => ['en', 'de', 'it'],
                'default_locale' => 'en',
                'disabled_firewalls' => ['admin'],
            ]
        ];

        $extension->load($config, $container);

        // Use has() instead of hasDefinition() to catch aliases or synthetic services
        $this->assertTrue($container->has('tmi_translation.translation.entity_translator'), 'EntityTranslator service should be registered');
        $this->assertTrue($container->has('tmi_translation.utils.attribute_helper'), 'AttributeHelper service should be registered');
        $this->assertTrue($container->has('tmi_translation.event_subscriber.locale_filter_configurator'), 'LocaleFilterConfigurator subscriber should be registered');
    }

    public function testPrependDoesNothing(): void
    {
        $container = new ContainerBuilder();
        $extension = new TmiTranslationExtension();

        // Call the empty prepend method, it should not throw any exceptions
        $extension->prepend($container);

        // Assert the container is still an instance of ContainerBuilder
        $this->assertInstanceOf(ContainerBuilder::class, $container);
    }

}
