<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Psr\Log\LoggerInterface;
use Tmi\TranslationBundle\Translation\Context\TranslationContext;
use Tmi\TranslationBundle\Translation\TypeDefaultResolver;
use Tmi\TranslationBundle\Utils\AttributeHelper;
use Tmi\TranslationBundle\Utils\ReflectionHelper;

/**
 * Handler for Doctrine embeddable objects.
 *
 * Uses per-property resolution where each embedded property resolves independently
 * through a three-level cascade (entity-property -> embeddable-property -> embeddable-class).
 *
 * Resolution order (highest priority first):
 * 1. Property-level attribute (on the embeddable's property itself)
 * 2. Class-level attribute (on the embeddable class)
 * 3. Default: use class default value
 */
final class EmbeddedHandler implements TranslationHandlerInterface
{
    private LoggerInterface|null $logger = null;

    public function __construct(
        private readonly AttributeHelper $attributeHelper,
        private readonly TypeDefaultResolver $typeDefaultResolver,
        LoggerInterface|null $logger = null,
    ) {
        $this->logger = $logger;
    }

    public function setLogger(LoggerInterface|null $logger): void
    {
        $this->logger = $logger;
    }

    public function supports(TranslationContext $context): bool
    {
        return null !== $context->getProperty() && $this->attributeHelper->isEmbedded($context->getProperty());
    }

    /**
     * Unified per-property resolution for embedded objects.
     *
     * $context->isShared() always returns a clone, never the original instance --
     * sharing one embeddable object across locale variants is its own Doctrine
     * footgun: a mutation made through one locale variant would bleed into every
     * sibling still holding that same instance, before anything is even flushed. The
     * clone's property values are left untouched (unlike the normal cascade below,
     * which resets non-shared inner properties), so the persisted data stays
     * identical across siblings -- only the in-memory object identity differs.
     *
     * $context->isEmpty() clears every inner property carrying its own
     * #[EmptyOnTranslate], unless the outer property itself already resolved to
     * "empty" -- in which case EntityTranslator's caller drops the whole value and
     * this per-inner-property cascade never has anything left to contribute.
     *
     * Otherwise, clones the embedded object and resolves each property through the
     * three-level cascade:
     * 1. Property-level attribute (most specific)
     * 2. Class-level attribute (default for all properties)
     * 3. No attribute: reset to class default value
     *
     * @throws \ReflectionException
     */
    public function translate(TranslationContext $context): mixed
    {
        $embeddable = $context->getSubject();
        assert(\is_object($embeddable));

        if ($context->isShared()) {
            return clone $embeddable;
        }

        if ($context->isEmpty()) {
            $parentProperty = $context->getProperty();
            if (null !== $parentProperty && $this->attributeHelper->isEmptyOnTranslate($parentProperty)) {
                return null;
            }

            $clone      = clone $embeddable;
            $reflection = new \ReflectionClass($clone);
            $changed    = false;

            foreach (ReflectionHelper::getHierarchyProperties($reflection) as $prop) {
                if ($this->attributeHelper->isSharedAmongstTranslations($prop)) {
                    continue;
                }

                if ($this->attributeHelper->isEmptyOnTranslate($prop)) {
                    $this->clearProperty($clone, $prop);
                    $changed = true;
                }
            }

            return $changed ? $clone : $embeddable;
        }

        $reflection = new \ReflectionClass($embeddable);

        // Validate the embeddable class (cached after first call)
        $this->attributeHelper->validateEmbeddableClass($reflection, $this->logger);

        // Detect class-level attributes
        $classShared = $this->attributeHelper->classHasSharedAmongstTranslations($reflection);
        $classEmpty  = $this->attributeHelper->classHasEmptyOnTranslate($reflection);

        if ($classShared) {
            $this->logDebug('Class-level attribute detected: SharedAmongstTranslations', [
                'class' => $reflection->getName(),
            ]);
        }

        if ($classEmpty) {
            $this->logDebug('Class-level attribute detected: EmptyOnTranslate', [
                'class' => $reflection->getName(),
            ]);
        }

        // Clone the embedded object for selective modification
        $clone = clone $embeddable;

        foreach (ReflectionHelper::getHierarchyProperties($reflection) as $prop) {
            // Resolve effective attribute via three-level cascade
            $resolved = $this->resolvePropertyAttribute($prop, $classShared, $classEmpty);

            // SharedAmongstTranslations always keeps cloned value regardless of copySource
            if ('shared' === $resolved) {
                continue;
            }

            // copy_source: false -- all non-shared properties get type-safe defaults
            if (false === $context->getCopySource()) {
                if ($this->attributeHelper->isEmptyOnTranslate($prop)) {
                    $this->logDebug('EmptyOnTranslate has no effect on embedded property when copy_source is false', [
                        'property' => $prop->getName(),
                    ]);
                }

                $this->applyTypeDefault($clone, $prop);

                continue;
            }

            if ('empty' === $resolved) {
                // Clear the property value
                $this->clearProperty($clone, $prop);

                continue;
            }

            // resolved === 'default' -- use the class default value (not copied from original)
            $this->resetToDefault($clone, $prop);
        }

        return $clone;
    }

    /**
     * Resolves the effective attribute for a property using the three-level cascade.
     *
     * @return string 'shared', 'empty', or 'default'
     */
    private function resolvePropertyAttribute(
        \ReflectionProperty $prop,
        bool $classShared,
        bool $classEmpty,
    ): string {
        $propShared = $this->attributeHelper->isSharedAmongstTranslations($prop);
        $propEmpty  = $this->attributeHelper->isEmptyOnTranslate($prop);

        // Determine effective attribute
        $classLevel    = $classShared ? 'shared' : ($classEmpty ? 'empty' : 'none');
        $propertyLevel = $propShared ? 'shared' : ($propEmpty ? 'empty' : 'none');

        // Property overrides class (most specific wins)
        if ('none' !== $propertyLevel) {
            $resolved = $propertyLevel;

            // Log override if class-level exists and differs
            if ('none' !== $classLevel && $classLevel !== $propertyLevel) {
                $this->logDebug('Property {property}: class={class_attr}, property={prop_attr} -> resolved: {resolved} (property override)', [
                    'property'   => $prop->getName(),
                    'class_attr' => $classLevel,
                    'prop_attr'  => $propertyLevel,
                    'resolved'   => $resolved,
                ]);
            } else {
                $this->logDebug('Property {property}: class={class_attr}, property={prop_attr} -> resolved: {resolved}', [
                    'property'   => $prop->getName(),
                    'class_attr' => $classLevel,
                    'prop_attr'  => $propertyLevel,
                    'resolved'   => $resolved,
                ]);
            }

            return $resolved;
        }

        // No property-level attribute: use class-level if present
        if ('none' !== $classLevel) {
            $this->logDebug('Property {property}: class={class_attr}, property=none -> resolved: {resolved}', [
                'property'   => $prop->getName(),
                'class_attr' => $classLevel,
                'resolved'   => $classLevel,
            ]);

            return $classLevel;
        }

        // No attribute at any level
        $this->logDebug('Property {property}: class=none, property=none -> resolved: default', [
            'property' => $prop->getName(),
        ]);

        return 'default';
    }

    private function clearProperty(object $clone, \ReflectionProperty $prop): void
    {
        if (true !== $prop->getType()?->allowsNull()) {
            // Non-nullable property: use type-safe default instead of null
            $this->applyTypeDefault($clone, $prop);

            return;
        }

        $setter = 'set'.ucfirst($prop->getName());

        $reflection = new \ReflectionClass($clone);
        if ($reflection->hasMethod($setter)) {
            $reflection->getMethod($setter)->invoke($clone, null);
        } else {
            $prop->setValue($clone, null);
        }
    }

    private function applyTypeDefault(object $clone, \ReflectionProperty $prop): void
    {
        try {
            $default = $this->typeDefaultResolver->resolve($prop);
            $prop->setValue($clone, $default);
        } catch (\LogicException) {
            // Non-nullable object/enum in embeddable: keep cloned value as safety fallback
            $this->logDebug('Cannot resolve type-safe default for embedded property, keeping source value', [
                'property' => $prop->getName(),
            ]);
        }
    }

    private function resetToDefault(object $clone, \ReflectionProperty $prop): void
    {
        if ($prop->hasDefaultValue()) {
            $prop->setValue($clone, $prop->getDefaultValue());
        }
        // If no default and not nullable, leave the cloned value as-is
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logDebug(string $message, array $context = []): void
    {
        $this->logger?->debug('[TMI Translation][Embedded] '.$message, $context);
    }
}
