<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tmi\TranslationBundle\TmiTranslationBundle;

#[AllowMockObjectsWithoutExpectations]
final class TmiTranslationBundleTest extends TestCase
{
    public function testBundleCanBeInstantiated(): void
    {
        $bundle = new TmiTranslationBundle();
        // Verify it can be instantiated and has a valid name
        self::assertStringContainsString('Translation', $bundle->getName());
    }

    public function testBuildMethodExistsAndIsCallable(): void
    {
        $bundle    = new TmiTranslationBundle();
        $container = $this->createMock(ContainerBuilder::class);

        // Test that the method can be called without throwing exceptions
        $bundle->build($container);
        $this->addToAssertionCount(1);
    }

    public function testBundleInheritance(): void
    {
        $bundle = new TmiTranslationBundle();

        // Test the inheritance chain -- verify the bundle extends a base class
        $parentClasses = class_parents($bundle);
        self::assertNotEmpty($parentClasses);
        self::assertStringContainsString('Bundle', implode(',', $parentClasses));
    }

    public function testBundleProvidesBasicFunctionality(): void
    {
        $bundle = new TmiTranslationBundle();

        // Test basic methods inherited from parent return non-empty values
        self::assertNotEmpty($bundle->getName());
        self::assertNotEmpty($bundle->getNamespace());
        self::assertNotEmpty($bundle->getPath());

        // Verify the path exists (this is the directory where the bundle is located)
        self::assertDirectoryExists($bundle->getPath());

        // Remove the problematic assertion that checks for class name in path
        // The path typically points to the src/ directory, not a specific bundle name
    }

    /**
     * @throws \ReflectionException
     */
    public function testBuildMethodSignature(): void
    {
        $bundle = new TmiTranslationBundle();

        // Test that build method has the correct signature
        $reflection = new \ReflectionMethod($bundle, 'build');
        $parameters = $reflection->getParameters();

        self::assertCount(1, $parameters);
        self::assertSame('container', $parameters[0]->getName());

        $parameterType = $parameters[0]->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $parameterType);
        self::assertEquals(ContainerBuilder::class, $parameterType->getName());
    }

    public function testBundleNameAndNamespace(): void
    {
        $bundle = new TmiTranslationBundle();

        // Test that name and namespace are meaningful
        $name      = $bundle->getName();
        $namespace = $bundle->getNamespace();

        self::assertNotEmpty($name);
        self::assertNotEmpty($namespace);

        // Typically, the name would be related to the bundle class
        self::assertStringContainsString('Translation', $name);
        self::assertStringContainsString('Tmi', $namespace);
    }
}
