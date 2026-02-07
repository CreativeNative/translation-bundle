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
     * SharedAmongstTranslations is not supported for unidirectional ManyToMany collections.
     *
     * @return Collection<int, mixed>
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): Collection
    {
        $prop = $args->getProperty();
        if (null === $prop) {
            /** @var Collection<int, mixed> */
            return new ArrayCollection();
        }

        // Check for SharedAmongstTranslations attribute
        $sharedAttrs = $prop->getAttributes(SharedAmongstTranslations::class);
        if (count($sharedAttrs) > 0) {
            $data = $args->getDataToBeTranslated();
            throw new \RuntimeException(sprintf('SharedAmongstTranslations is not allowed on unidirectional ManyToMany associations. Property "%s" of class "%s" is invalid.', $prop->getName(), \is_object($data) ? $data::class : 'unknown'));
        }

        return $this->translate($args);
    }

    /**
     * @return Collection<int, mixed>
     */
    public function handleEmptyOnTranslate(TranslationArgs $args): Collection
    {
        return new ArrayCollection();
    }

    /**
     * Translate the collection items and replace the collection entries with translated items.
     *
     * @return Collection<int, mixed>
     */
    public function translate(TranslationArgs $args): Collection
    {
        $newOwner = $args->getTranslatedParent();
        $property = $args->getProperty();

        if (!\is_object($newOwner)) {
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

        /** @var bool $isOwningSide */
        $isOwningSide = $association['isOwningSide'] ?? false;
        if (true !== $isOwningSide) {
            throw new \RuntimeException(sprintf('Property "%s" on "%s" is not the owning side of the relation.', $property->name, $newOwner::class));
        }

        /** @var string $fieldName */
        $fieldName = $association['fieldName'];
        $accessor  = new PropertyAccessor();

        if (!property_exists($newOwner, $fieldName)) {
            throw new \RuntimeException(sprintf('Field "%s" not found in class "%s".', $fieldName, $newOwner::class));
        }

        /** @var Collection<int, mixed>|null $collection */
        $collection = $accessor->getValue($newOwner, $fieldName);

        if (!$collection instanceof Collection) {
            /** @var Collection<int, mixed> $collection */
            $collection = new ArrayCollection();
            $accessor->setValue($newOwner, $fieldName, $collection);
        }

        // ---------- CRITICAL FIX ----------
        // copy items to translate BEFORE clearing the collection.
        $sourceData = $args->getDataToBeTranslated();
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

        // clear target collection (safe now because we have a copy)
        $collection->clear();

        $targetLocale = $args->getTargetLocale();

        foreach ($itemsToTranslate as $item) {
            if (!$item instanceof TranslatableInterface || !\is_string($targetLocale)) {
                continue;
            }

            $translated = $this->translator->translate($item, $targetLocale);

            if (!$collection->contains($translated)) {
                $collection->add($translated);
            }
        }

        return $collection;
    }
}
