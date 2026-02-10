<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Tmi\TranslationBundle\DependencyInjection\Compiler\AttributeValidationPass;

final class AttributeValidationPassTest extends TestCase
{
    public function testProcessSkipsWhenDoctrineNotConfigured(): void
    {
        $container = new ContainerBuilder();
        $pass      = new AttributeValidationPass();

        // The container does NOT have the entity manager
        self::assertFalse($container->has('doctrine.orm.entity_manager'));

        // process() should execute and immediately return without error
        $pass->process($container);

        // Assert entity manager still does not exist
        self::assertFalse($container->has('doctrine.orm.entity_manager'));
    }

    public function testProcessSkipsWhenNoMetadataDriversFound(): void
    {
        $container = new ContainerBuilder();

        // Register entity manager but no metadata drivers
        $container->register('doctrine.orm.entity_manager', \stdClass::class);

        $pass = new AttributeValidationPass();
        $pass->process($container);

        // Should complete without throwing exception
        self::assertTrue($container->has('doctrine.orm.entity_manager'));
    }

    public function testProcessPassesForValidEntities(): void
    {
        $container = new ContainerBuilder();

        // Register entity manager
        $container->register('doctrine.orm.entity_manager', \stdClass::class);

        // Register metadata driver pointing to Valid fixtures
        $driverDef = new Definition(\stdClass::class);
        $driverDef->addArgument([__DIR__.'/../../Fixtures/Validation/Valid']);
        $container->setDefinition('doctrine.orm.default_attribute_metadata_driver', $driverDef);

        $pass = new AttributeValidationPass();

        // Should complete without throwing exception
        $pass->process($container);

        // Assert container still has entity manager
        self::assertTrue($container->has('doctrine.orm.entity_manager'));
    }

    public function testProcessDetectsMissingLocaleProperty(): void
    {
        $container = new ContainerBuilder();

        // Register entity manager
        $container->register('doctrine.orm.entity_manager', \stdClass::class);

        // Register metadata driver pointing to NoLocale fixtures
        $driverDef = new Definition(\stdClass::class);
        $driverDef->addArgument([__DIR__.'/../../Fixtures/Validation/NoLocale']);
        $container->setDefinition('doctrine.orm.default_attribute_metadata_driver', $driverDef);

        $pass = new AttributeValidationPass();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Missing locale property');
        $this->expectExceptionMessage('TranslatableTrait');

        $pass->process($container);
    }

    public function testProcessDetectsPropertyAttributeConflict(): void
    {
        $container = new ContainerBuilder();

        // Register entity manager
        $container->register('doctrine.orm.entity_manager', \stdClass::class);

        // Register metadata driver pointing to Conflict fixtures
        $driverDef = new Definition(\stdClass::class);
        $driverDef->addArgument([__DIR__.'/../../Fixtures/Validation/Conflict']);
        $container->setDefinition('doctrine.orm.default_attribute_metadata_driver', $driverDef);

        $pass = new AttributeValidationPass();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('SharedAmongstTranslations');
        $this->expectExceptionMessage('EmptyOnTranslate');

        $pass->process($container);
    }

    public function testProcessDetectsReadonlyEmptyConflict(): void
    {
        $container = new ContainerBuilder();

        // Register entity manager
        $container->register('doctrine.orm.entity_manager', \stdClass::class);

        // Register metadata driver pointing to ReadonlyEmpty fixtures
        $driverDef = new Definition(\stdClass::class);
        $driverDef->addArgument([__DIR__.'/../../Fixtures/Validation/ReadonlyEmpty']);
        $container->setDefinition('doctrine.orm.default_attribute_metadata_driver', $driverDef);

        $pass = new AttributeValidationPass();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('readonly');

        $pass->process($container);
    }

    public function testProcessCollectsMultipleErrors(): void
    {
        $container = new ContainerBuilder();

        // Register entity manager
        $container->register('doctrine.orm.entity_manager', \stdClass::class);

        // Register metadata driver pointing to multiple invalid fixture subdirectories
        $driverDef = new Definition(\stdClass::class);
        $driverDef->addArgument([
            __DIR__.'/../../Fixtures/Validation/NoLocale',
            __DIR__.'/../../Fixtures/Validation/Conflict',
        ]);
        $container->setDefinition('doctrine.orm.default_attribute_metadata_driver', $driverDef);

        $pass = new AttributeValidationPass();

        try {
            $pass->process($container);
            self::fail('Expected LogicException to be thrown');
        } catch (\LogicException $e) {
            // Should contain errors from both fixtures
            self::assertStringContainsString('Missing locale property', $e->getMessage());
            self::assertStringContainsString('SharedAmongstTranslations', $e->getMessage());
            self::assertStringContainsString('2 error(s)', $e->getMessage());
        }
    }

    public function testProcessDetectsClassLevelAttributeConflict(): void
    {
        $container = new ContainerBuilder();

        // Register entity manager
        $container->register('doctrine.orm.entity_manager', \stdClass::class);

        // Register metadata driver pointing to ClassConflict fixtures
        $driverDef = new Definition(\stdClass::class);
        $driverDef->addArgument([__DIR__.'/../../Fixtures/Validation/ClassConflict']);
        $container->setDefinition('doctrine.orm.default_attribute_metadata_driver', $driverDef);

        $pass = new AttributeValidationPass();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Class-level attribute conflict');

        $pass->process($container);
    }

    public function testProcessHandlesEmptyArgumentsGracefully(): void
    {
        $container = new ContainerBuilder();

        // Register entity manager
        $container->register('doctrine.orm.entity_manager', \stdClass::class);

        // Register metadata driver with empty arguments
        $driverDef = new Definition(\stdClass::class);
        $container->setDefinition('doctrine.orm.default_attribute_metadata_driver', $driverDef);

        $pass = new AttributeValidationPass();
        $pass->process($container);

        // Should complete without error
        self::assertTrue($container->has('doctrine.orm.entity_manager'));
    }

    public function testProcessHandlesNonArrayDirectoriesArgument(): void
    {
        $container = new ContainerBuilder();

        // Register entity manager
        $container->register('doctrine.orm.entity_manager', \stdClass::class);

        // Register metadata driver with non-array first argument
        $driverDef = new Definition(\stdClass::class);
        $driverDef->addArgument('not-an-array');
        $container->setDefinition('doctrine.orm.default_attribute_metadata_driver', $driverDef);

        $pass = new AttributeValidationPass();
        $pass->process($container);

        // Should complete without error
        self::assertTrue($container->has('doctrine.orm.entity_manager'));
    }

    public function testProcessHandlesNonExistentDirectory(): void
    {
        $container = new ContainerBuilder();

        // Register entity manager
        $container->register('doctrine.orm.entity_manager', \stdClass::class);

        // Register metadata driver pointing to non-existent directory
        $driverDef = new Definition(\stdClass::class);
        $driverDef->addArgument(['/non/existent/directory']);
        $container->setDefinition('doctrine.orm.default_attribute_metadata_driver', $driverDef);

        $pass = new AttributeValidationPass();
        $pass->process($container);

        // Should complete without error
        self::assertTrue($container->has('doctrine.orm.entity_manager'));
    }

    public function testProcessHandlesNonStringDirectoryInArray(): void
    {
        $container = new ContainerBuilder();

        // Register entity manager
        $container->register('doctrine.orm.entity_manager', \stdClass::class);

        // Register metadata driver with mixed array (string and non-string)
        $driverDef = new Definition(\stdClass::class);
        $driverDef->addArgument([
            __DIR__.'/../../Fixtures/Validation/Valid',
            123, // Non-string value
            null,
        ]);
        $container->setDefinition('doctrine.orm.default_attribute_metadata_driver', $driverDef);

        $pass = new AttributeValidationPass();
        $pass->process($container);

        // Should complete without error (only Valid entities found)
        self::assertTrue($container->has('doctrine.orm.entity_manager'));
    }

    public function testProcessSkipsEdgeCaseFiles(): void
    {
        $container = new ContainerBuilder();

        // Register entity manager
        $container->register('doctrine.orm.entity_manager', \stdClass::class);

        // Register metadata driver pointing to EdgeCases directory
        // This directory contains: abstract classes, interfaces, traits, non-translatable classes,
        // files without namespace, files without class, non-PHP files
        $driverDef = new Definition(\stdClass::class);
        $driverDef->addArgument([__DIR__.'/../../Fixtures/Validation/EdgeCases']);
        $container->setDefinition('doctrine.orm.default_attribute_metadata_driver', $driverDef);

        $pass = new AttributeValidationPass();
        $pass->process($container);

        // Should complete without error - all files in EdgeCases should be skipped
        self::assertTrue($container->has('doctrine.orm.entity_manager'));
    }
}
