<?php

namespace TMI\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\OneToMany;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslator;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * Handles translation of OneToMany relations.
 */
class BidirectionalOneToManyHandler implements TranslationHandlerInterface
{
    public function __construct(private readonly AttributeHelper $attributeHelper, private readonly EntityTranslator $translator, private readonly EntityManagerInterface $em)
    {
    }

    public function supports(TranslationArgs $args): bool
    {
        if ($args->getProperty() && $this->attributeHelper->isOneToMany($args->getProperty())) {
            $arguments = $args->getProperty()->getAttributes(OneToMany::class)[0]->getArguments();

            if (array_key_exists('mappedBy', $arguments) && null !== $arguments['mappedBy']) {
                return true;
            }
        }

        return false;
    }

    public function handleSharedAmongstTranslations(TranslationArgs $args): never
    {
        $data = $args->getDataToBeTranslated();
        $message =
            '%class%::%prop% is a Bidirectional OneToMany, it cannot be shared '.
            'amongst translations. Either remove the SharedAmongstTranslation '.
            'attribute or choose another association type.';

        throw new \ErrorException(
            strtr($message, [
                '%class%' => $data::class,
                '%prop%'  => $args->getProperty()->name,
            ])
        );
    }

    public function handleEmptyOnTranslate(TranslationArgs $args)
    {
        return new ArrayCollection();
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
        // their owner to $newOwner
        foreach ($newCollection as $key => $item) {
            $reflection = new \ReflectionProperty($item::class, $mappedBy);

            // Translate the item
            $subTranslationArgs = new TranslationArgs($item, $args->getSourceLocale(), $args->getTargetLocale())
                ->setTranslatedParent($newOwner)
                ->setProperty($reflection)
            ;

            $itemTrans = $this->translator->processTranslation($subTranslationArgs);

            // Set the translated item new owner
            $reflection->setValue($itemTrans, $newOwner);

            $newCollection[$key] = $itemTrans;
        }

        return $newCollection;
    }
}
