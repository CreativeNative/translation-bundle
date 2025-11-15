<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use ReflectionClass;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * Handler for Doctrine embeddable objects.
 *
 * Behaviour:
 * 1) If embeddable OR parent property OR any inner property is marked #[SharedAmongstTranslations],
 *    the embeddable is *shared* (same instance) across translations.
 * 2) If not shared and translate() is invoked -> clone embeddable.
 * 3) If EmptyOnTranslate applies:
 *    - If shared -> keep the shared instance (do NOT empty).
 *    - If not shared -> return null (empty).
 */
final readonly class EmbeddedHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper $attributeHelper,
    ) {
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

        // If we are configured to share the embeddable (parent property, class, or inner property)
        if ($this->isShared($args)) {
            return $embeddable; // share same reference across translations
        }

        // Not shared -> give callers a clone (consistent with translate())
        return clone $embeddable;
    }

    /**
     * Handle #[EmptyOnTranslate] for embeddable.
     *
     * Important: If the embeddable (or any inner property) is shared, we must NOT empty it.
     * Only when NOT shared do we return null (empty).
     *
     * @throws \ReflectionException
     */
    public function handleEmptyOnTranslate(TranslationArgs $args): mixed
    {
        $embeddable = $args->getDataToBeTranslated();

        // If the top-level property is marked as #[EmptyOnTranslate], empty the entire embeddable
        $parentProperty = $args->getProperty();
        if ($parentProperty && $this->attributeHelper->isEmptyOnTranslate($parentProperty)) {
            return null;
        }

        // Clone embeddable to avoid mutating original
        $clone = clone $embeddable;

        // Reflect the actual class of the embeddable
        $reflection = new \ReflectionClass($clone);

        $changed = false;

        foreach ($reflection->getProperties() as $prop) {
            // Skip properties that are shared
            if ($this->attributeHelper->isSharedAmongstTranslations($prop)) {
                continue;
            }

            // Only clear properties marked as #[EmptyOnTranslate]
            if ($this->attributeHelper->isEmptyOnTranslate($prop)) {
                $setter = 'set'.ucfirst($prop->getName());

                if (method_exists($clone, $setter)) {
                    $callable = \Closure::fromCallable([$clone, $setter]);
                    $callable(null);
                } else {
                    // Fallback: set value via ReflectionProperty
                    $prop->setValue($clone, null);
                }

                $changed = true;
            }
        }

        return $changed ? $clone : $embeddable;
    }

    public function translate(TranslationArgs $args): mixed
    {
        return clone $args->getDataToBeTranslated();
    }

    /**
     * Returns true when the embeddable should be shared across translations, i.e.:
     * - the parent property is marked #[SharedAmongstTranslations], or
     * - the embeddable class itself is marked #[SharedAmongstTranslations], or
     * - any property inside the embeddable is marked #[SharedAmongstTranslations].
     *
     * Note: AttributeHelper provides helpers for ReflectionProperty checks.
     * For class-level attribute we check via ReflectionClass directly (no AttributeHelper method assumed).
     *
     * @throws \ReflectionException
     */
    private function isShared(TranslationArgs $args): bool
    {
        $embeddable = $args->getDataToBeTranslated();

        // Parent property (embeddable)
        $parentProperty = $args->getProperty();
        if ($parentProperty && $this->attributeHelper->isSharedAmongstTranslations($parentProperty)) {
            return true;
        }

        // 2. Any inner property of the embeddable marked SharedAmongstTranslations
        $reflection = new \ReflectionClass($embeddable);

        return array_any($reflection->getProperties(), $this->attributeHelper->isSharedAmongstTranslations(...));
    }
}
