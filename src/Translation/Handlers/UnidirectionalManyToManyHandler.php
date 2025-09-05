<?php

namespace TMI\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToMany;
use ReflectionException;
use ReflectionProperty;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslator;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * Handles unidirectional ManyToMany translation.
 */
final class UnidirectionalManyToManyHandler implements TranslationHandlerInterface
{
    public function __construct(
        private readonly AttributeHelper $attributeHelper,
        private readonly EntityTranslator $translator,
        private readonly EntityManagerInterface $em
    ) {}

    public function supports(TranslationArgs $args): bool
    {
        $data = $args->getDataToBeTranslated();
        if (!$data instanceof Collection) {
            return false;
        }

        // Unidirectional ManyToMany check: only verify the property is a ManyToMany
        return $args->getProperty()?->getAttributes(ManyToMany::class) !== [];
    }

    /**
     * Handles translation of shared-across-translations fields.
     * @throws ReflectionException
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): Collection
    {
        return $this->translate($args);
    }

    /**
     * Handles fields marked as empty-on-translate.
     */
    public function handleEmptyOnTranslate(TranslationArgs $args): null|Collection
    {
        return null;
    }

    /**
     * Translates the collection of items for a unidirectional ManyToMany association.
     *
     * @throws ReflectionException
     */
    public function translate(TranslationArgs $args): Collection
    {
        $newOwner = $args->getTranslatedParent();
        $property = $args->getProperty();

        if (!$property) {
            return new ArrayCollection();
        }

        $reflection = new ReflectionProperty($newOwner::class, $property->name);
        $reflection->setAccessible(true);

        $collection = $reflection->getValue($newOwner);
        if (!$collection instanceof Collection) {
            $collection = new ArrayCollection();
            $reflection->setValue($newOwner, $collection);
        }

        // Clear collection
        foreach ($collection as $key => $item) {
            $collection->remove($key);
        }

        // Translate and re-add items
        foreach ($args->getDataToBeTranslated() as $itemToBeTranslated) {
            $itemTrans = $this->translator->translate($itemToBeTranslated, $args->getTargetLocale());

            if (!$collection->contains($itemTrans)) {
                $collection->add($itemTrans);
            }
        }

        return $collection;
    }
}
