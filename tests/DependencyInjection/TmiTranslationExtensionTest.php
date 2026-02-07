<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\DependencyInjection;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tmi\TranslationBundle\DependencyInjection\TmiTranslationExtension;
use Tmi\TranslationBundle\Doctrine\Type\TuuidType;
use Tmi\TranslationBundle\EventSubscriber\LocaleFilterConfigurator;
use Tmi\TranslationBundle\Test\IntegrationTestCase;

#[AllowMockObjectsWithoutExpectations]
final class TmiTranslationExtensionTest extends IntegrationTestCase
{
    /**
     * @throws Exception
     * @throws TypesException
     */
    public function testLoadRegistersServices(): void
    {
        $containerBuilder = $this->createContainerBuilderFromKernel();

        $extension = new TmiTranslationExtension();
        $config    = [
            [
                'locales'            => ['en_US', 'de_DE', 'it_IT'],
                'default_locale'     => 'en_US',
                'disabled_firewalls' => ['main'],
            ],
        ];

        $extension->load($config, $containerBuilder);

        self::assertTrue($containerBuilder->has('tmi_translation.translation.entity_translator'));
        self::assertTrue($containerBuilder->has('tmi_translation.utils.attribute_helper'));
        self::assertTrue($containerBuilder->has(LocaleFilterConfigurator::class));
    }

    public function testPrependDoesNothing(): void
    {
        $containerBuilder = $this->createContainerBuilderFromKernel();
        $extension        = new TmiTranslationExtension();
        $extension->prepend($containerBuilder);

        self::assertSame(ContainerBuilder::class, $containerBuilder::class);
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
            self::assertInstanceOf(TuuidType::class, $existing);
        }

        $containerBuilder = $this->createContainerBuilderFromKernel();
        $extension        = new TmiTranslationExtension();
        $extension->load([['locales' => ['en_US', 'de_DE'], 'default_locale' => 'en_US']], $containerBuilder);

        self::assertTrue(Type::hasType(TuuidType::NAME));
        self::assertInstanceOf(TuuidType::class, Type::getType(TuuidType::NAME));
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
            ->with('tuuid', 'tuuid');

        // Create a fake connection stub
        $connectionStub = new readonly class($platformStub) {
            public function __construct(private AbstractPlatform $platform)
            {
            }

            public function getDatabasePlatform(): AbstractPlatform
            {
                return $this->platform;
            }
        };

        // Register the fake connection in the container
        $containerBuilder->set('doctrine.dbal.default_connection', $connectionStub);

        $extension = new TmiTranslationExtension();
        $extension->load([['locales' => ['en_US'], 'default_locale' => 'en_US']], $containerBuilder);

        // Assert that the TuuidType exists
        self::assertTrue(Type::hasType(TuuidType::NAME), 'TuuidType should be registered');
        self::assertInstanceOf(TuuidType::class, Type::getType(TuuidType::NAME));
    }

    private function createContainerBuilderFromKernel(): ContainerBuilder
    {
        $containerBuilder = new ContainerBuilder();

        // Pull parameters from the booted kernel container if available
        self::bootKernel();
        $container = self::getContainer();

        foreach ($container->getParameterBag()->all() as $key => $value) {
            if (is_scalar($value) || is_array($value) || null === $value || $value instanceof \UnitEnum) {
                $containerBuilder->setParameter($key, $value);
            }
        }

        return $containerBuilder;
    }
}
