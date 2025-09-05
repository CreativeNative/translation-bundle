<?php

namespace TMI\TranslationBundle\Test;

use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use TMI\TranslationBundle\TmiTranslationBundle;

final class TmiTranslationBundleTest extends TestCase
{
    public function testBundleCanBeInstantiated(): void
    {
        $bundle = new TmiTranslationBundle();
        // Test that it's a Symfony Bundle
        $this->assertInstanceOf(Bundle::class, $bundle);
    }

    public function testBuildMethodExistsAndIsCallable(): void
    {
        $bundle = new TmiTranslationBundle();
        $container = $this->createMock(ContainerBuilder::class);

        // Test that the method exists
        $this->assertTrue(method_exists($bundle, 'build'));

        // Test that the method can be called without throwing exceptions
        try {
            $bundle->build($container);
            $this->assertTrue(true, 'build() method executed successfully');
        } catch (Exception $e) {
            $this->fail('build() method threw an exception: ' . $e->getMessage());
        }
    }

    public function testBundleInheritance(): void
    {
        $bundle = new TmiTranslationBundle();

        // Test the inheritance chain
        $parentClass = get_parent_class($bundle);
        $this->assertSame(Bundle::class, $parentClass);
    }

    public function testBundleProvidesBasicFunctionality(): void
    {
        $bundle = new TmiTranslationBundle();

        // Test basic methods inherited from parent
        $this->assertIsString($bundle->getName());
        $this->assertIsString($bundle->getNamespace());
        $this->assertIsString($bundle->getPath());

        // Verify the path exists (this is the directory where the bundle is located)
        $this->assertDirectoryExists($bundle->getPath());

        // Remove the problematic assertion that checks for class name in path
        // The path typically points to the src/ directory, not a specific bundle name
    }

    /**
     * @throws ReflectionException
     */
    public function testBuildMethodSignature(): void
    {
        $bundle = new TmiTranslationBundle();

        // Test that build method has the correct signature
        $reflection = new ReflectionMethod($bundle, 'build');
        $parameters = $reflection->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertSame('container', $parameters[0]->getName());

        $parameterType = $parameters[0]->getType();
        $this->assertInstanceOf(\ReflectionType::class, $parameterType);
        $this->assertEquals(ContainerBuilder::class, $parameterType->getName());
    }

    public function testBundleNameAndNamespace(): void
    {
        $bundle = new TmiTranslationBundle();

        // Test that name and namespace are meaningful
        $name = $bundle->getName();
        $namespace = $bundle->getNamespace();

        $this->assertNotEmpty($name);
        $this->assertNotEmpty($namespace);

        // Typically, the name would be related to the bundle class
        $this->assertStringContainsString('Translation', $name);
        $this->assertStringContainsString('TMI', $namespace);
    }
}
