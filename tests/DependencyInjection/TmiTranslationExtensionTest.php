<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\DependencyInjection;

use Doctrine\DBAL\Connection;
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
use Tmi\TranslationBundle\Translation\EntityTranslator;
use Tmi\TranslationBundle\Translation\TypeDefaultResolver;

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
        $extension->load([['default_locale' => 'en_US']], $containerBuilder);

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
        $containerBuilder->setParameter('kernel.enabled_locales', ['en_US']);
        $containerBuilder->setParameter('kernel.default_locale', 'en_US');

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
        $extension->load([['default_locale' => 'en_US']], $containerBuilder);

        // Assert that the TuuidType exists
        self::assertTrue(Type::hasType(TuuidType::NAME), 'TuuidType should be registered');
        self::assertInstanceOf(TuuidType::class, Type::getType(TuuidType::NAME));
    }

    /**
     * @throws Exception
     * @throws TypesException
     */
    public function testTuuidTypeMappingWithRealConnectionMock(): void
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('kernel.enabled_locales', ['en_US']);
        $containerBuilder->setParameter('kernel.default_locale', 'en_US');

        $platformMock = $this->createMock(AbstractPlatform::class);
        $platformMock->expects(self::once())
            ->method('registerDoctrineTypeMapping')
            ->with('tuuid', 'tuuid');

        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->method('getDatabasePlatform')
            ->willReturn($platformMock);

        $containerBuilder->set('doctrine.dbal.default_connection', $connectionMock);

        $extension = new TmiTranslationExtension();
        $extension->load([['default_locale' => 'en_US']], $containerBuilder);

        // The expects(once()) on platformMock will verify registerDoctrineTypeMapping was called
    }

    /**
     * @throws Exception
     * @throws TypesException
     */
    public function testLoadRegistersTypeWhenNotYetRegistered(): void
    {
        $registry   = Type::getTypeRegistry();
        $reflection = new \ReflectionProperty($registry, 'instances');

        /** @var array<string, Type> $original */
        $original = $reflection->getValue($registry);

        // Temporarily remove the tuuid type
        $modified = $original;
        unset($modified[TuuidType::NAME]);
        $reflection->setValue($registry, $modified);

        // Also remove from reverse index
        $reverseReflection = new \ReflectionProperty($registry, 'instancesReverseIndex');
        /** @var array<int, string> $originalReverse */
        $originalReverse = $reverseReflection->getValue($registry);

        try {
            self::assertFalse(Type::hasType(TuuidType::NAME), 'TuuidType should be unregistered');

            $containerBuilder = $this->createContainerBuilderFromKernel();
            $extension        = new TmiTranslationExtension();
            $extension->load([['default_locale' => 'en_US']], $containerBuilder);

            self::assertTrue(Type::hasType(TuuidType::NAME), 'TuuidType should be re-registered by load()');
            self::assertInstanceOf(TuuidType::class, Type::getType(TuuidType::NAME));
        } finally {
            // Restore original state to avoid polluting other tests
            $reflection->setValue($registry, $original);
            $reverseReflection->setValue($registry, $originalReverse);
        }
    }

    /**
     * @throws Exception
     * @throws TypesException
     */
    public function testLoadSetsLoggerToNullWhenLoggingDisabled(): void
    {
        $containerBuilder = $this->createContainerBuilderFromKernel();

        $extension = new TmiTranslationExtension();
        $extension->load([['default_locale' => 'en_US', 'enable_logging' => false]], $containerBuilder);

        $definition = $containerBuilder->getDefinition(EntityTranslator::class);
        self::assertNull($definition->getArgument('$logger'));
    }

    public function testLoadThrowsWhenEnabledLocalesNotConfigured(): void
    {
        $containerBuilder = new ContainerBuilder();

        $extension = new TmiTranslationExtension();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('framework.enabled_locales to be configured');

        $extension->load([['default_locale' => 'en_US']], $containerBuilder);
    }

    public function testLoadThrowsWhenEnabledLocalesIsEmpty(): void
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('kernel.enabled_locales', []);
        $containerBuilder->setParameter('kernel.default_locale', 'en_US');

        $extension = new TmiTranslationExtension();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('framework.enabled_locales to be configured');

        $extension->load([['default_locale' => 'en_US']], $containerBuilder);
    }

    public function testLoadThrowsWhenDefaultLocaleNotInEnabledLocales(): void
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('kernel.enabled_locales', ['en_US', 'de_DE']);
        $containerBuilder->setParameter('kernel.default_locale', 'en_US');

        $extension = new TmiTranslationExtension();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The default_locale "fr_FR" must be included in framework.enabled_locales [en_US, de_DE]');

        $extension->load([['default_locale' => 'fr_FR']], $containerBuilder);
    }

    /**
     * @throws Exception
     * @throws TypesException
     */
    public function testLoggingDefaultsToDisabled(): void
    {
        $containerBuilder = $this->createContainerBuilderFromKernel();

        $extension = new TmiTranslationExtension();
        $extension->load([['default_locale' => 'en_US']], $containerBuilder);

        $definition = $containerBuilder->getDefinition(EntityTranslator::class);
        self::assertNull($definition->getArgument('$logger'));
    }

    public function testLoadThrowsOnRemovedLocalesConfigKey(): void
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('kernel.enabled_locales', ['en_US']);
        $containerBuilder->setParameter('kernel.default_locale', 'en_US');

        $extension = new TmiTranslationExtension();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"tmi_translation.locales" option was removed in v2.0');

        $extension->load([['locales' => ['en_US'], 'default_locale' => 'en_US']], $containerBuilder);
    }

    public function testLoadThrowsOnRemovedLoggingConfigKey(): void
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('kernel.enabled_locales', ['en_US']);
        $containerBuilder->setParameter('kernel.default_locale', 'en_US');

        $extension = new TmiTranslationExtension();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"tmi_translation.logging" option was removed in v2.0');

        $extension->load([['logging' => ['enabled' => true], 'default_locale' => 'en_US']], $containerBuilder);
    }

    /**
     * @throws Exception
     * @throws TypesException
     */
    public function testCopySourceDefaultsToFalse(): void
    {
        $containerBuilder = $this->createContainerBuilderFromKernel();

        $extension = new TmiTranslationExtension();
        $extension->load([['default_locale' => 'en_US']], $containerBuilder);

        self::assertFalse($containerBuilder->getParameter('tmi_translation.copy_source'));
    }

    /**
     * @throws Exception
     * @throws TypesException
     */
    public function testCopySourceCanBeSetToTrue(): void
    {
        $containerBuilder = $this->createContainerBuilderFromKernel();

        $extension = new TmiTranslationExtension();
        $extension->load([['default_locale' => 'en_US', 'copy_source' => true]], $containerBuilder);

        self::assertTrue($containerBuilder->getParameter('tmi_translation.copy_source'));
    }

    /**
     * @throws Exception
     * @throws TypesException
     */
    public function testTypeDefaultResolverIsRegistered(): void
    {
        $containerBuilder = $this->createContainerBuilderFromKernel();

        $extension = new TmiTranslationExtension();
        $extension->load([['default_locale' => 'en_US']], $containerBuilder);

        self::assertTrue($containerBuilder->has(TypeDefaultResolver::class));
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
