<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\DependencyInjection;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use Tmi\TranslationBundle\DependencyInjection\TmiTranslationExtension;
use Tmi\TranslationBundle\Doctrine\Type\TuuidType;
use Tmi\TranslationBundle\EventSubscriber\LocaleFilterConfigurator;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Doctrine\DBAL\Platforms\AbstractPlatform;

final class TmiTranslationExtensionTest extends IntegrationTestCase
{
    private function createContainerBuilderFromKernel(): ContainerBuilder
    {
        $containerBuilder = new ContainerBuilder();

        // Pull parameters from the booted kernel container if available
        if (self::$container === null) {
            self::bootKernel();
            self::$container = method_exists(self::class, 'getContainer')
                ? self::getContainer()
                : self::$kernel->getContainer();
        }

        if (self::$container !== null) {
            foreach (self::$container->getParameterBag()->all() as $key => $value) {
                $containerBuilder->setParameter($key, $value);
            }
        }

        return $containerBuilder;
    }


    /**
     * @throws Exception
     * @throws TypesException
     */
    public function testLoadRegistersServices(): void
    {
        $containerBuilder = $this->createContainerBuilderFromKernel();

        $extension = new TmiTranslationExtension();
        $config = [
            [
                'locales' => ['en_US', 'de_DE', 'it_IT'],
                'default_locale' => 'en_US',
                'disabled_firewalls' => ['main'],
            ],
        ];

        $extension->load($config, $containerBuilder);

        $this->assertTrue($containerBuilder->has('tmi_translation.translation.entity_translator'));
        $this->assertTrue($containerBuilder->has('tmi_translation.utils.attribute_helper'));
        $this->assertTrue($containerBuilder->has(LocaleFilterConfigurator::class));
    }

    public function testPrependDoesNothing(): void
    {
        $containerBuilder = $this->createContainerBuilderFromKernel();
        $extension = new TmiTranslationExtension();
        $extension->prepend($containerBuilder);

        $this->assertInstanceOf(ContainerBuilder::class, $containerBuilder);
    }

    /**
     * @throws TypesException
     * @throws Exception
     */
    public function testTuuidTypeIsRegistered(): void
    {
        // Ensure the type exists and is correct
        if (Type::hasType(TuuidType::NAME)) {
            $existing = Type::getType(TuuidType::NAME);
            $this->assertInstanceOf(TuuidType::class, $existing);
        }

        $containerBuilder = $this->createContainerBuilderFromKernel();
        $extension = new TmiTranslationExtension();
        $extension->load([['locales' => ['en_US', 'de_DE'], 'default_locale' => 'en_US']], $containerBuilder);

        $this->assertTrue(Type::hasType(TuuidType::NAME));
        $this->assertInstanceOf(TuuidType::class, Type::getType(TuuidType::NAME));
    }

    /**
     * @throws Exception
     * @throws TypesException
     */
    public function testTuuidTypeMapping(): void
    {
        $containerBuilder = new ContainerBuilder();

        // Create a fake DBAL platform stub
        $platformStub = $this->createMock(AbstractPlatform::class);
        $platformStub->method('registerDoctrineTypeMapping')
            ->with('tuuid', 'guid');

        // Create a fake connection stub
        $connectionStub = new readonly class($platformStub) {
            public function __construct(private AbstractPlatform $platform) {}
            public function getDatabasePlatform(): AbstractPlatform { return $this->platform; }
        };

        // Register the fake connection in the container
        $containerBuilder->set('doctrine.dbal.default_connection', $connectionStub);

        $extension = new TmiTranslationExtension();
        $extension->load([['locales' => ['en_US'], 'default_locale' => 'en_US']], $containerBuilder);

        // Assert that the TuuidType exists
        $this->assertTrue(Type::hasType(TuuidType::NAME), 'TuuidType should be registered');
        $this->assertInstanceOf(TuuidType::class, Type::getType(TuuidType::NAME));
    }
}
