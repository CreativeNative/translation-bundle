<?php

namespace TMI\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\OneToMany;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslator;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * Collection handler, used for ManyToMany bidirectional association.
 */
class CollectionHandler implements TranslationHandlerInterface
{
    public function __construct(private readonly AttributeHelper $attributeHelper, private readonly EntityManagerInterface $em, private readonly EntityTranslator $translator)
    {
    }

    public function supports(TranslationArgs $args): bool
    {
        if (!$args->getDataToBeTranslated() instanceof Collection) {
            return false;
        }

        if ($args->getProperty() && $this->attributeHelper->isManyToMany($args->getProperty())) {
            $arguments = $args->getProperty()->getAttributes(ManyToMany::class)[0]->getArguments();

            if (array_key_exists('mappedBy', $arguments) && null !== $arguments['mappedBy']) {
                return true;
            }
        }

        return false;
    }

    public function handleSharedAmongstTranslations(TranslationArgs $args)
    {
        /** @var Collection $collection */
        $collection = $args->getDataToBeTranslated();
        $newCollection = clone $collection;
        $newOwner = $args->getTranslatedParent();

        // Get the owner's "mappedBy"
        $associations = $this->em->getClassMetadata($newOwner::class)->getAssociationMappings();
        $association = $associations[$args->getProperty()->name];
        $mappedBy = $association['mappedBy'];

        // Iterate through collection and set
        // their owner to $newOwner
        foreach ($newCollection as $key => $item) {
            $reflection = new \ReflectionProperty($item::class, $mappedBy);

            // Translate the item
            $subTranslationArgs =
                new TranslationArgs($item, $args->getSourceLocale(), $args->getTargetLocale())
                    ->setTranslatedParent($newOwner)
                    ->setProperty($reflection)
            ;

            $itemTrans = $this->translator->processTranslation($subTranslationArgs);

            // Set the translated item new owner
            $reflection->setValue($itemTrans, new ArrayCollection([$newOwner]));
            $newCollection[$key] = $itemTrans;
        }

        return $newCollection;
    }

    public function handleEmptyOnTranslate(TranslationArgs $args)
    {
        return new ArrayCollection([]);
    }

    public function translate(TranslationArgs $args)
    {
        /** @var Collection $collection */
        $collection = $args->getDataToBeTranslated();
        $newCollection = clone $collection;
        $newOwner = $args->getTranslatedParent();

        // Get the owner's "mappedBy"
        $associations = $this->em->getClassMetadata($newOwner::class)->getAssociationMappings();
        $association = $associations[$args->getProperty()->name];
        $mappedBy = $association['mappedBy'];

        // Iterate through collection and set
        // their owner owner to $newOwner
        foreach ($newCollection as $key => $item) {
            $reflection = new \ReflectionProperty($item::class, $mappedBy);

            // Set item's owner to null
            $reflection->setValue($item, new ArrayCollection([]));

            // Translate the item
            $itemTrans = $this->translator->translate($item, $args->getTargetLocale());

            // Set the translated item new owner
            $reflection->setValue($itemTrans, new ArrayCollection([$newOwner]));
            $newCollection[$key] = $itemTrans;
        }

        return $newCollection;
    }
}
