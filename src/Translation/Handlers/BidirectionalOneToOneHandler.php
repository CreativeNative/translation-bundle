<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\OneToOne;
use ErrorException;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * Handles translation of one-to-one bidirectional association.
 *
 * Was renamed from BidirectionalAssociationHandler
 */
final readonly class BidirectionalOneToOneHandler implements TranslationHandlerInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private PropertyAccessor $propertyAccessor,
        private AttributeHelper $attributeHelper
    ) {
    }

    public function supports(TranslationArgs $args): bool
    {
        $entity = $args->getDataToBeTranslated();

        if (!$entity instanceof TranslatableInterface) {
            return false;
        }

        $property = $args->getProperty();
        if (!$property || !$this->attributeHelper->isOneToOne($property)) {
            return false;
        }

        $attributes = $property->getAttributes(OneToOne::class);
        if (empty($attributes)) {
            return false;
        }

        $arguments = $attributes[0]->getArguments();

        // With OneToOne, there can be mappedBy or inversedBy
        return (isset($arguments['mappedBy'])) || (isset($arguments['inversedBy']));
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
