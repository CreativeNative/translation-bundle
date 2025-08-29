<?php

namespace TMI\TranslationBundle\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\OneToOne;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * Handles translation of one-to-one-bidirectional association.
 *
 * @todo   : Next major release, rename to BidirectionalManyToManyHandler.
 */
class BidirectionalAssociationHandler implements TranslationHandlerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PropertyAccessor       $propertyAccessor,
        private readonly AttributeHelper        $attributeHelper
    )
    {
    }

    public function supports(TranslationArgs $args): bool
    {
        if (null === $args->getProperty()) {
            return false;
        }

        if ($args->getProperty() && $this->attributeHelper->isOneToOne($args->getProperty())) {
            $arguments = $args->getProperty()->getAttributes(OneToOne::class)[0]->getArguments();

            if (array_key_exists('mappedBy', $arguments) && null !== $arguments['mappedBy']) {
                return true;
            }
        }

        return false;
    }

    public function handleSharedAmongstTranslations(TranslationArgs $args)
    {
        if (true === $this->attributeHelper->isOneToOne($args->getProperty())) {
            $data = $args->getDataToBeTranslated();
            $message =
                '%class%::%prop% is a Bidirectional OneToOne, it cannot be shared ' .
                'amongst translations. Either remove the @SharedAmongstTranslation ' .
                'annotation or choose another association type.';

            throw new \ErrorException(
                strtr($message, [
                    '%class%' => $data::class,
                    '%prop%' => $args->getProperty()->name,
                ])
            );
        }

        return $args->getDataToBeTranslated();
    }

    public function handleEmptyOnTranslate(TranslationArgs $args)
    {
        return null;
    }

    public function translate(TranslationArgs $args)
    {
        // $data is the child association
        $clone = clone $args->getDataToBeTranslated();

        // Get the correct parent association with the fieldName
        $fieldName = $args->getProperty()->name;
        $associations = $this->em->getClassMetadata($clone::class)->getAssociationMappings();

        foreach ($associations as $association) {
            if ($fieldName === $association['inversedBy']) {
                $parentFieldName = $association['fieldName'];
            }
        }

        $clone->setLocale($args->getTargetLocale());

        // Set the invertedAssociation with the clone parent.
        $this->propertyAccessor->setValue($clone, $parentFieldName, $args->getTranslatedParent());

        return $clone;
    }
}
