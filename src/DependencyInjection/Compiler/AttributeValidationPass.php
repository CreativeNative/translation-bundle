<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Exception\ValidationException;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * Validates attribute usage on all Doctrine-mapped TranslatableInterface entities at compile time.
 *
 * Detects:
 * - Class-level attribute conflicts (Shared + Empty)
 * - Property-level attribute conflicts (Shared + Empty, readonly + Empty)
 * - Missing locale property
 *
 * Throws LogicException during cache:warmup if validation fails.
 */
final class AttributeValidationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Early return if Doctrine is not configured
        if (!$container->has('doctrine.orm.entity_manager')) {
            return;
        }

        $translatableClasses = $this->discoverTranslatableClasses($container);

        $errors = [];
        foreach ($translatableClasses as $class) {
            $this->validateEntity($class, $errors);
        }

        if ([] !== $errors) {
            throw new \LogicException(sprintf("TMI Translation Bundle: Compile-time validation failed with %d error(s):\n\n%s", count($errors), implode("\n", array_map(static fn (string $e) => "- {$e}", $errors))));
        }
    }

    /**
     * Discover all Doctrine-mapped TranslatableInterface entities.
     *
     * @return array<\ReflectionClass<object>>
     */
    private function discoverTranslatableClasses(ContainerBuilder $container): array
    {
        $classes = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            // Look for Doctrine's attribute metadata driver definitions
            if (!str_contains($id, 'attribute_metadata_driver')) {
                continue;
            }

            $arguments = $definition->getArguments();
            if ([] === $arguments) {
                continue;
            }

            // First argument is an array of entity directory paths
            $directories = $arguments[0] ?? [];
            if (!is_array($directories)) {
                continue;
            }

            foreach ($directories as $directory) {
                if (is_string($directory)) {
                    $this->scanDirectoryForTranslatables($directory, $classes);
                }
            }
        }

        return $classes;
    }

    /**
     * Recursively scan directory for TranslatableInterface implementors.
     *
     * @param array<\ReflectionClass<object>> $classes
     */
    private function scanDirectoryForTranslatables(string $directory, array &$classes): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if (!$file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            $className = $this->extractClassName($file->getPathname());
            if (null === $className) {
                continue;
            }

            if (!class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);

            // Skip abstract classes, interfaces, traits
            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                continue;
            }

            // Only include TranslatableInterface implementors
            if ($reflection->implementsInterface(TranslatableInterface::class)) {
                $classes[] = $reflection;
            }
        }
    }

    /**
     * Extract fully qualified class name from PHP file.
     */
    private function extractClassName(string $filePath): string|null
    {
        $contents = file_get_contents($filePath);
        if (false === $contents) {
            return null;
        }

        // Extract namespace
        if (1 !== preg_match('/namespace\s+([^;]+);/', $contents, $namespaceMatches)) {
            return null;
        }
        $namespace = trim($namespaceMatches[1]);

        // Extract class name (allowing for final, abstract, readonly modifiers)
        if (1 !== preg_match('/(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)/', $contents, $classMatches)) {
            return null;
        }
        $className = trim($classMatches[1]);

        return $namespace.'\\'.$className;
    }

    /**
     * Validate entity for attribute conflicts and locale field presence.
     *
     * @param \ReflectionClass<object> $class
     * @param array<string>            $errors
     */
    private function validateEntity(\ReflectionClass $class, array &$errors): void
    {
        $attributeHelper = new AttributeHelper();

        // Check class-level attribute conflicts
        if ($attributeHelper->classHasSharedAmongstTranslations($class)
            && $attributeHelper->classHasEmptyOnTranslate($class)) {
            $errors[] = sprintf(
                '%s: Class-level attribute conflict - cannot use both #[SharedAmongstTranslations] and #[EmptyOnTranslate] on the same class',
                $class->getName(),
            );
        }

        // Validate all properties (including inherited)
        $currentClass = $class;
        do {
            foreach ($currentClass->getProperties() as $property) {
                try {
                    $attributeHelper->validateProperty($property);
                } catch (ValidationException $e) {
                    foreach ($e->getErrors() as $error) {
                        $errors[] = sprintf('%s: %s', $class->getName(), $error->getMessage());
                    }
                }
            }
            $currentClass = $currentClass->getParentClass();
        } while (false !== $currentClass);

        // Check locale field presence
        $this->validateLocaleField($class, $errors);
    }

    /**
     * Validate that the entity has a locale property.
     *
     * @param \ReflectionClass<object> $class
     * @param array<string>            $errors
     */
    private function validateLocaleField(\ReflectionClass $class, array &$errors): void
    {
        $currentClass = $class;
        do {
            foreach ($currentClass->getProperties() as $property) {
                if ('locale' === $property->getName()) {
                    return; // Found locale property
                }
            }
            $currentClass = $currentClass->getParentClass();
        } while (false !== $currentClass);

        // No locale property found
        $errors[] = sprintf(
            '%s: Missing locale property - TranslatableInterface requires a locale property. Use TranslatableTrait or manually define a "locale" property.',
            $class->getName(),
        );
    }
}
