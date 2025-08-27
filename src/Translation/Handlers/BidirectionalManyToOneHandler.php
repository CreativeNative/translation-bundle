<?php

namespace TMI\TranslationBundle\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToOne;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslator;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * Handles translation of ManyToOne relations.
 */
class BidirectionalManyToOneHandler implements TranslationHandlerInterface
{
    public function __construct(private readonly AttributeHelper $attributeHelper, private readonly EntityManagerInterface $em, private readonly PropertyAccessorInterface $propertyAccessor, private readonly EntityTranslator $translator)
    {
    }

    public function supports(TranslationArgs $args): bool
    {
        if ($args->getProperty() && $this->attributeHelper->isManyToOne($args->getProperty())) {
            $arguments = $args->getProperty()->getAttributes(ManyToOne::class)[0]->getArguments();

            if (array_key_exists('inversedBy', $arguments) && null !== $arguments['inversedBy']) {
                return true;
            }
        }

        return false;
    }

    public function handleSharedAmongstTranslations(TranslationArgs $args): never
    {
        $data = $args->getDataToBeTranslated();
        $message =
            '%class%::%prop% is a Bidirectional ManyToOne, it cannot be shared '.
            'amongst translations. Either remove the @SharedAmongstTranslation '.
            'annotation or choose another association type.';

        throw new \ErrorException(
            strtr($message, [
                '%class%' => $data::class,
                '%prop%'  => $args->getProperty()->name,
            ])
        );
    }

    public function handleEmptyOnTranslate(TranslationArgs $args)
    {
        return null;
    }

    public function translate(TranslationArgs $args)
    {
        // $data is the child association
        $clone = clone $args->getDataToBeTranslated();
        $parentFieldName = null;

        // Get the correct parent association with the fieldName
        $fieldName = $args->getProperty()->name;
        $associations = $this->em->getClassMetadata($clone::class)->getAssociationMappings();

        foreach ($associations as $key => $association) {
            if ($fieldName === $key) {
                $parentFieldName = $association['fieldName'];
            }
        }

        if (null !== $parentFieldName) {
            $clone->setLocale($args->getTargetLocale());

            // Set the invertedAssociation with the clone parent.
            $this->propertyAccessor->setValue($clone, $parentFieldName, $args->getTranslatedParent());

            return $clone;
        }

        // If no parent field is found, were in the parent, translate it rather than the child.
        return $this->translator->translate($args->getDataToBeTranslated(), $args->getTargetLocale());
    }
}
