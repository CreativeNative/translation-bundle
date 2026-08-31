<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Exception\ValidationException;
use Tmi\TranslationBundle\Utils\AttributeHelper;
use Tmi\TranslationBundle\Utils\ReflectionHelper;

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

        if ([] === $translatableClasses) {
            $container->log($this, sprintf(
                '0 translatable entities discovered under the configured Doctrine attribute mapping directories. '
                .'This can be legitimate (no %s entities yet), but it can also mean doctrine-bundle changed the '
                .'shape of its attribute metadata driver service definitions and this bundle\'s compile-time '
                .'discovery silently found nothing to validate.',
                TranslatableInterface::class,
            ));
        }

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
            if (!$file instanceof \SplFileInfo || !$file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            foreach ($this->extractClassNames($file->getPathname()) as $className) {
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
    }

    /**
     * Extract every fully qualified class name genuinely declared in a PHP file.
     *
     * Walks PHP's own tokens instead of matching a regex against the raw text,
     * so a class name mentioned in a comment or string literal before the real
     * declaration can never be mistaken for it, `Foo::class` constant fetches
     * and anonymous classes (`new class`) are never mistaken for declarations,
     * and a file that declares several classes yields all of them.
     *
     * @return list<string>
     */
    private function extractClassNames(string $filePath): array
    {
        $tokens = \PhpToken::tokenize((string) file_get_contents($filePath));

        $namespace  = null;
        $classNames = [];

        foreach ($tokens as $index => $token) {
            if ($token->is(\T_NAMESPACE)) {
                $namespace = $this->nextSignificantToken($tokens, $index)?->text;
                continue;
            }

            if (!$token->is(\T_CLASS)) {
                continue;
            }

            $previous          = $this->previousSignificantToken($tokens, $index);
            $isNotADeclaration = $previous instanceof \PhpToken && ($previous->is(\T_DOUBLE_COLON) || $previous->is(\T_NEW));

            if ($isNotADeclaration) {
                continue;
            }

            $next = $this->nextSignificantToken($tokens, $index);
            if (null !== $namespace && $next instanceof \PhpToken) {
                $classNames[] = $namespace.'\\'.$next->text;
            }
        }

        return $classNames;
    }

    /**
     * The next token that is not whitespace, a comment, or an opening tag.
     *
     * @param array<\PhpToken> $tokens
     */
    private function nextSignificantToken(array $tokens, int $index): \PhpToken|null
    {
        $count = count($tokens);
        $i     = $index + 1;
        while ($i < $count && $tokens[$i]->isIgnorable()) {
            ++$i;
        }

        return $i < $count ? $tokens[$i] : null;
    }

    /**
     * The previous token that is not whitespace, a comment, or an opening tag.
     *
     * @param array<\PhpToken> $tokens
     */
    private function previousSignificantToken(array $tokens, int $index): \PhpToken|null
    {
        $i = $index - 1;
        while ($i >= 0 && $tokens[$i]->isIgnorable()) {
            --$i;
        }

        return $i >= 0 ? $tokens[$i] : null;
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

        // Validate all properties (including private ones inherited from parents)
        foreach (ReflectionHelper::getHierarchyProperties($class) as $property) {
            try {
                $attributeHelper->validateProperty($property);
            } catch (ValidationException $e) {
                foreach ($e->getErrors() as $error) {
                    $errors[] = sprintf('%s: %s', $class->getName(), $error->getMessage());
                }
            }
        }

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
        foreach (ReflectionHelper::getHierarchyProperties($class) as $property) {
            if ('locale' === $property->getName()) {
                return; // Found locale property
            }
        }

        // No locale property found
        $errors[] = sprintf(
            '%s: Missing locale property - TranslatableInterface requires a locale property. Use TranslatableTrait or manually define a "locale" property.',
            $class->getName(),
        );
    }
}
