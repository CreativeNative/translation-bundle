<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToMany;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Context\PropertyTranslationContext;
use Tmi\TranslationBundle\Translation\Context\TranslationContext;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * Handles ManyToMany unidirectional associations during translation.
 */
final readonly class UnidirectionalManyToManyHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper $attributeHelper,
        private EntityTranslatorInterface $translator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(TranslationContext $context): bool
    {
        // The value of a ManyToMany property is the Collection, never the entity itself --
        // guarding on TranslatableInterface here made supports() always false.
        if (!$context instanceof PropertyTranslationContext || !$context->getValue() instanceof Collection) {
            return false;
        }

        $property = $context->getProperty();
        if (null === $property || !$this->attributeHelper->isManyToMany($property)) {
            return false;
        }

        $attributes = $property->getAttributes(ManyToMany::class);
        if ([] === $attributes) {
            return false;
        }

        $arguments = $attributes[0]->getArguments();

        // Unidirectional = neither mappedBy nor inversedBy set
        return !isset($arguments['mappedBy']) && !isset($arguments['inversedBy']);
    }

    /**
     * $context->isShared(): SharedAmongstTranslations is not supported for unidirectional
     * ManyToMany collections -- throws, unless the property turns out not to actually
     * carry the attribute (defensive; the translator only sets isShared() when it does).
     *
     * $context->isEmpty(): returns a fresh empty collection.
     *
     * Otherwise: translates the collection items and replaces the collection entries
     * with translated items.
     *
     * @return Collection<int, mixed>
     */
    public function translate(TranslationContext $context): Collection
    {
        \assert($context instanceof PropertyTranslationContext);

        if ($context->isShared()) {
            $prop = $context->getProperty();
            if (null === $prop) {
                return new ArrayCollection();
            }

            // Check for SharedAmongstTranslations attribute
            $sharedAttrs = $prop->getAttributes(SharedAmongstTranslations::class);
            if (count($sharedAttrs) > 0) {
                $data = $context->getValue();

                throw new \RuntimeException(sprintf('SharedAmongstTranslations is not allowed on unidirectional ManyToMany associations. Property "%s" of class "%s" is invalid.', $prop->getName(), \is_object($data) ? $data::class : 'unknown'));
            }

            return $this->translateCollection($context);
        }

        if ($context->isEmpty()) {
            return new ArrayCollection();
        }

        return $this->translateCollection($context);
    }

    /**
     * The whole collection is handed to {@see EntityTranslatorInterface::preload()} once,
     * before the loop below: one batched lookup query per item class rather than one per
     * item, since each item is otherwise its own translate() call with its own internal
     * single-entity preload(). preload() ignores non-translatable items on its own, so the
     * mixed translatable/non-translatable collections this handler supports are safe to
     * hand it whole.
     *
     * @return Collection<int, mixed>
     */
    private function translateCollection(PropertyTranslationContext $context): Collection
    {
        $newOwner = $context->getTranslatedParent();
        $property = $context->getProperty();

        if (null === $newOwner) {
            throw new \RuntimeException('No translated parent provided.');
        }

        if (null === $property) {
            throw new \RuntimeException(sprintf('No property given for parent of class "%s".', $newOwner::class));
        }

        $meta         = $this->entityManager->getClassMetadata($newOwner::class);
        $associations = $meta->getAssociationMappings();
        $association  = $associations[$property->name] ?? null;

        if (null === $association) {
            throw new \RuntimeException(sprintf('Property "%s" is not a valid association in class "%s".', $property->name, $newOwner::class));
        }

        if (!$association->isOwningSide()) {
            throw new \RuntimeException(sprintf('Property "%s" on "%s" is not the owning side of the relation.', $property->name, $newOwner::class));
        }

        $fieldName = $association->fieldName;

        if (!property_exists($newOwner, $fieldName)) {
            throw new \RuntimeException(sprintf('Field "%s" not found in class "%s".', $fieldName, $newOwner::class));
        }

        $sourceData = $context->getValue();
        /** @var list<mixed> $itemsToTranslate */
        $itemsToTranslate = [];
        if ($sourceData instanceof Collection) {
            $itemsToTranslate = $sourceData->toArray();
        } elseif (\is_iterable($sourceData)) {
            /** @var iterable<mixed> $sourceData */
            foreach ($sourceData as $item) {
                $itemsToTranslate[] = $item;
            }
        }

        // Build a fresh collection instead of clearing the one currently on $newOwner: a
        // clone shares its collection instance with the source entity, so clearing it would
        // wipe the source's association -- and clearing a managed PersistentCollection whose
        // owner has no identifier yet makes the flush blow up. The caller assigns whatever
        // is returned here to the translated parent.
        /** @var Collection<int, mixed> $translatedItems */
        $translatedItems = new ArrayCollection();

        $targetLocale = $context->getTargetLocale();

        // One batched lookup for the whole collection instead of leaving each item's own
        // translate() call to query for itself: preload() groups translatable items by
        // class and issues one LocaleVariantFinder query per class, ignoring
        // non-translatable items and anything already cached. A collection of K
        // translatable items of one class then costs one query total here, not K.
        if (\is_string($targetLocale)) {
            $this->translator->preload($itemsToTranslate, $targetLocale);
        }

        foreach ($itemsToTranslate as $item) {
            if (!$item instanceof TranslatableInterface || !\is_string($targetLocale)) {
                // Non-translatable items (e.g. tags, categories) and the missing-target-locale
                // edge case are preserved as-is instead of being dropped -- mirrors
                // BidirectionalManyToManyHandler::translate(), which does the same.
                if (!$translatedItems->contains($item)) {
                    $translatedItems->add($item);
                }

                continue;
            }

            $translated = $this->translator->translate($item, $targetLocale);

            if (!$translatedItems->contains($translated)) {
                $translatedItems->add($translated);
            }
        }

        return $translatedItems;
    }
}
