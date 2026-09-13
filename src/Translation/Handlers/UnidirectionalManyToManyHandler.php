<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToMany;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Exception\SharedAssociationException;
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

    #[\Override]
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
     * $context->isShared(): the target is itself translatable, so sharing is refused.
     *
     * $context->isEmpty(): returns a fresh empty collection.
     *
     * Otherwise: translates the collection items and replaces the collection entries
     * with translated items.
     *
     * @return Collection<int, mixed>
     */
    #[\Override]
    public function translate(TranslationContext $context): Collection
    {
        \assert($context instanceof PropertyTranslationContext);

        if ($context->isShared()) {
            $prop = $context->getProperty();

            throw SharedAssociationException::forAssociation('unidirectional ManyToMany', null !== $prop ? $prop->class : 'unknown', null !== $prop ? $prop->name : 'unknown');
        }

        if ($context->isEmpty()) {
            return new ArrayCollection();
        }

        return $this->translateCollection($context);
    }

    /**
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
            throw new \RuntimeException(\sprintf('No property given for parent of class "%s".', $newOwner::class));
        }

        $meta         = $this->entityManager->getClassMetadata($newOwner::class);
        $associations = $meta->getAssociationMappings();
        $association  = $associations[$property->name] ?? null;

        if (null === $association) {
            throw new \RuntimeException(\sprintf('Property "%s" is not a valid association in class "%s".', $property->name, $newOwner::class));
        }

        if (!$association->isOwningSide()) {
            throw new \RuntimeException(\sprintf('Property "%s" on "%s" is not the owning side of the relation.', $property->name, $newOwner::class));
        }

        $fieldName = $association->fieldName;

        if (!property_exists($newOwner, $fieldName)) {
            throw new \RuntimeException(\sprintf('Field "%s" not found in class "%s".', $fieldName, $newOwner::class));
        }

        $sourceData = $context->getValue();
        /** @var list<mixed> $itemsToTranslate */
        $itemsToTranslate = [];
        if ($sourceData instanceof Collection) {
            $itemsToTranslate = $sourceData->toArray();
        } elseif (is_iterable($sourceData)) {
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
        CollectionTranslationSupport::preload($this->translator, $itemsToTranslate, $targetLocale);

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

            // No back-reference to touch on a unidirectional association, but adding the
            // source item would still persist a join row that crosses locales.
            if (CollectionTranslationSupport::isCycleGuardFallback($translated, $item, $targetLocale)) {
                continue;
            }

            if (!$translatedItems->contains($translated)) {
                $translatedItems->add($translated);
            }
        }

        return $translatedItems;
    }
}
