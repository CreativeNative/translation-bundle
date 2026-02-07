<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Psr\Log\LoggerInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Utils\AttributeHelper;

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
        LoggerInterface|null $logger = null,
    ) {
        $this->logger = $logger;
    }

    public function setLogger(LoggerInterface|null $logger): void
    {
        $this->logger = $logger;
    }

    public function supports(TranslationArgs $args): bool
    {
        return null !== $args->getProperty() && $this->attributeHelper->isEmbedded($args->getProperty());
    }

    /**
     * Handle #[SharedAmongstTranslations] for embeddable.
     *
     * If the embeddable is marked shared (via parent property, class, or inner property)
     * then return the same instance so siblings share it.
     * If not shared, return a clone so each locale gets its own copy.
     *
     * @throws \ReflectionException
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): mixed
    {
        $embeddable = $args->getDataToBeTranslated();
        assert(\is_object($embeddable));

        if ($this->isShared($args)) {
            return $embeddable;
        }

        return clone $embeddable;
    }

    /**
     * Handle #[EmptyOnTranslate] for embeddable.
     *
     * @throws \ReflectionException
     */
    public function handleEmptyOnTranslate(TranslationArgs $args): mixed
    {
        $embeddable = $args->getDataToBeTranslated();
        assert(\is_object($embeddable));

        $parentProperty = $args->getProperty();
        if (null !== $parentProperty && $this->attributeHelper->isEmptyOnTranslate($parentProperty)) {
            return null;
        }

        $clone      = clone $embeddable;
        $reflection = new \ReflectionClass($clone);
        $changed    = false;

        foreach ($reflection->getProperties() as $prop) {
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

    /**
     * Unified per-property resolution for embedded objects.
     *
     * Clones the embedded object and resolves each property through the three-level cascade:
     * 1. Property-level attribute (most specific)
     * 2. Class-level attribute (default for all properties)
     * 3. No attribute: reset to class default value
     *
     * @throws \ReflectionException
     */
    public function translate(TranslationArgs $args): mixed
    {
        $embeddable = $args->getDataToBeTranslated();
        assert(\is_object($embeddable));
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

        foreach ($reflection->getProperties() as $prop) {
            $resolved = $this->resolvePropertyAttribute($prop, $classShared, $classEmpty);

            if ('shared' === $resolved) {
                // Keep original value (already in clone via clone)
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
        $setter = 'set'.ucfirst($prop->getName());

        $reflection = new \ReflectionClass($clone);
        if ($reflection->hasMethod($setter)) {
            $reflection->getMethod($setter)->invoke($clone, null);
        } else {
            $prop->setValue($clone, null);
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
     * Returns true when the embeddable should be shared across translations, i.e.:
     * - the parent property is marked #[SharedAmongstTranslations], or
     * - the embeddable class itself is marked #[SharedAmongstTranslations], or
     * - any property inside the embeddable is marked #[SharedAmongstTranslations].
     *
     * @throws \ReflectionException
     */
    private function isShared(TranslationArgs $args): bool
    {
        $embeddable = $args->getDataToBeTranslated();
        assert(\is_object($embeddable));

        // Parent property (on the entity)
        $parentProperty = $args->getProperty();
        if (null !== $parentProperty && $this->attributeHelper->isSharedAmongstTranslations($parentProperty)) {
            return true;
        }

        // Class-level attribute on the embeddable
        $reflection = new \ReflectionClass($embeddable);
        if ($this->attributeHelper->classHasSharedAmongstTranslations($reflection)) {
            return true;
        }

        // Any inner property marked shared
        return array_any($reflection->getProperties(), $this->attributeHelper->isSharedAmongstTranslations(...));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logDebug(string $message, array $context = []): void
    {
        $this->logger?->debug('[TMI Translation][Embedded] '.$message, $context);
    }
}
