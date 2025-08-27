<?php

namespace TMI\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\PersistentCollection;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslator;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * Used for ManyToMany unidirectional association.
 */
class UnidirectionalManyToManyHandler implements TranslationHandlerInterface
{
    public function __construct(private readonly AttributeHelper $attributeHelper, private readonly EntityTranslator $translator, private readonly EntityManagerInterface $em)
    {
    }

    public function supports(TranslationArgs $args): bool
    {
        if (!$args->getDataToBeTranslated() instanceof Collection) {
            return false;
        }

        if ($args->getProperty() && $this->attributeHelper->isManyToMany($args->getProperty())) {
            $arguments = $args->getProperty()->getAttributes(ManyToMany::class)[0]->getArguments();

            if ((array_key_exists('mappedBy', $arguments) && null !== $arguments['mappedBy']) ||
                (array_key_exists('inversedBy', $arguments) && null !== $arguments['inversedBy'])) {
                return true;
            }
        }

        return false;
    }

    public function handleSharedAmongstTranslations(TranslationArgs $args)
    {
        return $this->translate($args);
    }

    public function handleEmptyOnTranslate(TranslationArgs $args)
    {
        return new ArrayCollection();
    }

    public function translate(TranslationArgs $args)
    {
        $newOwner = $args->getTranslatedParent();

        // Get the owner's fieldName
        $associations = $this->em->getClassMetadata($newOwner::class)->getAssociationMappings();
        $association = $associations[$args->getProperty()->name];
        $fieldName = $association['fieldName'];

        $reflection = new \ReflectionProperty($newOwner::class, $fieldName);

        /** @var PersistentCollection $collection */
        $collection = $reflection->getValue($newOwner);

        foreach ($collection as $key => $item) {
            $collection->remove($key);
        }

        foreach ($args->getDataToBeTranslated() as $itemtoBeTranslated) {
            $itemTrans = $this->translator->translate($itemtoBeTranslated, $args->getTargetLocale());

            if (!$collection->contains($itemTrans)) {
                $collection->add($itemTrans);
            }
        }

        return $collection;
    }
}
