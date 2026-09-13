<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Tmi\TranslationBundle\Doctrine\Root\RootAdopterRegistry;
use Tmi\TranslationBundle\Exception\TranslationRootContractException;
use Tmi\TranslationBundle\Exception\ValidationException;

/**
 * Collects the `tmi_translation.root_adopter` services into {@see RootAdopterRegistry}
 * -- the literal shape of {@see TranslationHandlerPass} -- and cross-checks them
 * against the translatable classes {@see AttributeValidationPass} found declaring a
 * root reference: every such class has EXACTLY one adopter (registered for the class
 * or an ancestor), and every adopter names a class under which at least one concrete
 * translatable declares a root reference.
 *
 * The adopter's class is read from the tag attribute `class`; no service is
 * instantiated at compile time. This lives in a compiler pass and NOT in the optional
 * cache warmer on purpose: `TranslatableEntityValidationWarmer::isOptional()` is true,
 * and Symfony's aggregate skips optional warmers on the ordinary rebuild path, so a
 * check placed there would never run after `cache:clear` + first request.
 *
 * Without a Doctrine entity manager in the container the cross-check is skipped (there
 * is no mapping to have discovered root references from); the registry is still
 * filled.
 */
final class RootAdopterPass implements CompilerPassInterface
{
    public const string TAG = 'tmi_translation.root_adopter';

    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(RootAdopterRegistry::class)) {
            return;
        }

        $registry = $container->findDefinition(RootAdopterRegistry::class);

        /** @var array<string, list<string>> $adopterClasses class => service ids */
        $adopterClasses = [];

        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            $attributes = $tags[0]                                      ?? null;
            $class      = \is_array($attributes) ? $attributes['class'] ?? null : null;

            if (!\is_string($class) || '' === $class) {
                throw TranslationRootContractException::forAdopterTagWithoutClass($id);
            }

            $registry->addMethodCall('addAdopter', [new Reference($id), $class]);
            $adopterClasses[$class][] = $id;
        }

        if (!$container->has('doctrine.orm.entity_manager')) {
            return;
        }

        /** @var list<string> $rootClasses */
        $rootClasses = $container->hasParameter(AttributeValidationPass::ROOT_CLASSES_PARAMETER)
            ? $container->getParameter(AttributeValidationPass::ROOT_CLASSES_PARAMETER)
            : [];

        $errors = [];

        foreach ($rootClasses as $rootClass) {
            $serving = [];

            foreach ($adopterClasses as $class => $ids) {
                if (is_a($rootClass, $class, true)) {
                    $serving = [...$serving, ...$ids];
                }
            }

            if ([] === $serving) {
                $errors[] = TranslationRootContractException::forMissingAdopter($rootClass)->getMessage();
            } elseif (\count($serving) > 1) {
                $errors[] = TranslationRootContractException::forDuplicateAdopter($rootClass, $serving)->getMessage();
            }
        }

        foreach ($adopterClasses as $class => $ids) {
            if (!array_any($rootClasses, static fn (string $rootClass): bool => is_a($rootClass, $class, true))) {
                $errors[] = TranslationRootContractException::forAdopterWithoutRoot($ids[0], $class)->getMessage();
            }
        }

        if ([] !== $errors) {
            throw ValidationException::fromMessages('Root adopter validation', $errors);
        }
    }
}
