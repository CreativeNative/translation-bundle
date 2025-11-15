<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToMany;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
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

    public function supports(TranslationArgs $args): bool
    {
        $entity = $args->getDataToBeTranslated();

        if (!$entity instanceof TranslatableInterface) {
            return false;
        }

        $property = $args->getProperty();
        if (!$property || !$this->attributeHelper->isManyToMany($property)) {
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
     * SharedAmongstTranslations is not supported for unidirectional ManyToMany collections.
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): Collection
    {
        /** @var Collection<int, object> $collection */
        $collection = $args->getDataToBeTranslated();

        $prop = $args->getProperty();
        if (null === $prop) {
            return $collection;
        }

        // Check for SharedAmongstTranslations attribute
        $sharedAttrs = $prop->getAttributes(SharedAmongstTranslations::class);
        if (count($sharedAttrs) > 0) {
            throw new \RuntimeException(sprintf('SharedAmongstTranslations is not allowed on unidirectional ManyToMany associations. Property "%s" of class "%s" is invalid.', $prop->getName(), $args->getDataToBeTranslated()::class));
        }

        return $this->translate($args);
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): Collection
    {
        return new ArrayCollection();
    }

    /**
     * Translate the collection items and replace the collection entries with translated items.
     */
    public function translate(TranslationArgs $args): Collection
    {
        $newOwner = $args->getTranslatedParent();
        $property = $args->getProperty();

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

        if (($association['isOwningSide'] ?? false) !== true) {
            throw new \RuntimeException(sprintf('Property "%s" on "%s" is not the owning side of the relation.', $property->name, $newOwner::class));
        }

        $fieldName = (string) $association['fieldName'];
        $accessor  = new PropertyAccessor();

        if (!property_exists($newOwner, $fieldName)) {
            throw new \RuntimeException(sprintf('Field "%s" not found in class "%s".', $fieldName, $newOwner::class));
        }

        /** @var Collection<int, object>|null $collection */
        $collection = $accessor->getValue($newOwner, $fieldName);

        if (!$collection instanceof Collection) {
            $collection = new ArrayCollection();
            $accessor->setValue($newOwner, $fieldName, $collection);
        }

        // ---------- CRITICAL FIX ----------
        // copy items to translate BEFORE clearing the collection.
        $itemsToTranslate = [];
        foreach ($args->getDataToBeTranslated() as $item) {
            $itemsToTranslate[] = $item;
        }

        // clear target collection (safe now because we have a copy)
        $collection->clear();

        foreach ($itemsToTranslate as $item) {
            $translated = $this->translator->translate($item, $args->getTargetLocale());

            if (!$collection->contains($translated)) {
                $collection->add($translated);
            }
        }

        return $collection;
    }
}
