<?php

declare(strict_types=1);

namespace TMI\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\PersistentCollection;
use ReflectionException;
use ReflectionProperty;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslatorInterface;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * Handles ManyToMany unidirectional associations during translation.
 */
final readonly class UnidirectionalManyToManyHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper           $attributeHelper,
        private EntityTranslatorInterface $translator,
        private EntityManagerInterface    $em,
    )
    {}

    public function supports(TranslationArgs $args): bool
    {
        $data = $args->getDataToBeTranslated();
        $property = $args->getProperty();

        if (!$data instanceof Collection || $property === null) {
            return false;
        }

        if (!$this->attributeHelper->isManyToMany($property)) {
            return false;
        }

        $attributes = $property->getAttributes(ManyToMany::class);
        if ($attributes === []) {
            return false;
        }

        $arguments = $attributes[0]->getArguments();

        // Unidirectional = neither mappedBy nor inversedBy is set
        return empty($arguments['mappedBy'] ?? null) && empty($arguments['inversedBy'] ?? null);
    }

    /**
     * @throws ReflectionException
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): Collection
    {
        return $this->translate($args);
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): Collection
    {
        return new ArrayCollection();
    }

    /**
     * @throws ReflectionException
     */
    public function translate(TranslationArgs $args): Collection
    {
        $newOwner = $args->getTranslatedParent();
        if ($newOwner === null || $args->getProperty() === null) {
            return new ArrayCollection();
        }

        // Find association metadata
        $associations = $this->em
            ->getClassMetadata($newOwner::class)
            ->getAssociationMappings();

        $association = $associations[$args->getProperty()->name] ?? null;
        if ($association === null) {
            return new ArrayCollection();
        }

        $fieldName = $association['fieldName'];

        $reflection = new ReflectionProperty($newOwner::class, $fieldName);

        $collection = $reflection->getValue($newOwner);
        assert($collection instanceof PersistentCollection);

        // Clear current collection
        $collection->clear();

        // Add translated items
        foreach ($args->getDataToBeTranslated() as $item) {
            $translated = $this->translator->translate($item, $args->getTargetLocale());
            if (!$collection->contains($translated)) {
                $collection->add($translated);
            }
        }

        return $collection;
    }
}
