<?php

namespace TMI\TranslationBundle\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\OneToOne;
use ErrorException;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * Handles translation of one-to-one bidirectional association.
 *
 * @todo Next major release, rename to BidirectionalManyToManyHandler.
 */
final class BidirectionalAssociationHandler implements TranslationHandlerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PropertyAccessor $propertyAccessor,
        private readonly AttributeHelper $attributeHelper
    ) {
    }

    public function supports(TranslationArgs $args): bool
    {
        if (null === $args->getProperty()) {
            return false;
        }

        if ($this->attributeHelper->isOneToOne($args->getProperty())) {
            $arguments = $args->getProperty()->getAttributes(OneToOne::class)[0]->getArguments();

            if (array_key_exists('mappedBy', $arguments) && null !== $arguments['mappedBy']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws ErrorException
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): mixed
    {
        if ($this->attributeHelper->isOneToOne($args->getProperty())) {
            $data = $args->getDataToBeTranslated();
            $message =
                '%class%::%prop% is a Bidirectional OneToOne, it cannot be shared ' .
                'amongst translations. Either remove the @SharedAmongstTranslation ' .
                'annotation or choose another association type.';

            throw new ErrorException(
                strtr($message, [
                    '%class%' => $data::class,
                    '%prop%' => $args->getProperty()->name,
                ])
            );
        }

        return $args->getDataToBeTranslated();
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): null
    {
        return null;
    }

    public function translate(TranslationArgs $args): mixed
    {
        $clone = clone $args->getDataToBeTranslated();
        $fieldName = $args->getProperty()->name;
        $associations = $this->em->getClassMetadata($clone::class)->getAssociationMappings();
        $parentFieldName = null;

        foreach ($associations as $association) {
            if ($fieldName === $association['inversedBy']) {
                $parentFieldName = $association['fieldName'];
            }
        }

        $clone->setLocale($args->getTargetLocale());

        if ($parentFieldName !== null) {
            $this->propertyAccessor->setValue($clone, $parentFieldName, $args->getTranslatedParent());
        }

        return $clone;
    }
}
