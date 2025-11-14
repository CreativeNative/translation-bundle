<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\DependencyInjection;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tmi\TranslationBundle\DependencyInjection\TmiTranslationExtension;
use Tmi\TranslationBundle\Doctrine\Type\TuuidType;
use Tmi\TranslationBundle\EventSubscriber\LocaleFilterConfigurator;

final class TmiTranslationExtensionTest extends TestCase
{
    /**
     * @throws TypesException
     * @throws Exception
     */
    public function testLoad(): void
    {
        $container = new ContainerBuilder();
        $extension = new TmiTranslationExtension();

        $config = [
            [
                'locales'            => ['en_US', 'de_DE', 'it_IT'],
                'default_locale'     => 'en_US',
                'disabled_firewalls' => ['main'],
            ],
        ];
        $extension->load($config, $container);

        $this->assertTrue(
            $container->has('tmi_translation.translation.entity_translator'),
            'EntityTranslator service should be registered',
        );
        $this->assertTrue(
            $container->has('tmi_translation.utils.attribute_helper'),
            'AttributeHelper service should be registered',
        );
        $this->assertTrue(
            $container->has(LocaleFilterConfigurator::class),
            'LocaleFilterConfigurator subscriber should be registered',
        );
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

    /**
     * @throws Exception
     * @throws TypesException
     * @throws \ReflectionException
     */
    public function testDoctrineTypeRegistration(): void
    {
        // Unregister TuuidType if it exists
        if (Type::hasType(TuuidType::NAME)) {
            $reflection = new \ReflectionClass(Type::class);
            $typesMap   = $reflection->getProperty('typesMap');
            $map        = $typesMap->getValue();
            unset($map[TuuidType::NAME]);
            $typesMap->setValue(null, $map);
        }

        $this->assertFalse(
            Type::hasType(TuuidType::NAME) && Type::getType(TuuidType::NAME) instanceof TuuidType,
            'TuuidType should not be registered yet',
        );

        $container = new ContainerBuilder();
        $extension = new TmiTranslationExtension();

        $config = [
            [
                'locales'        => ['en_US', 'de_DE'],
                'default_locale' => 'en_US',
            ],
        ];

        $extension->load($config, $container);

        $this->assertTrue(Type::hasType(TuuidType::NAME), 'TuuidType should be registered by the extension');

        $typeInstance = Type::getType(TuuidType::NAME);
        $this->assertInstanceOf(TuuidType::class, $typeInstance, 'Registered type should be an instance of TuuidType');
    }
}
