<?php

namespace TMI\TranslationBundle\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToOne;
use ErrorException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslatorInterface;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * Handles translation of ManyToOne relations.
 */
final readonly class BidirectionalManyToOneHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper           $attributeHelper,
        private EntityManagerInterface    $em,
        private PropertyAccessorInterface $propertyAccessor,
        private EntityTranslatorInterface $translator
    )
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

    /**
     * @throws ErrorException
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): mixed
    {
        $data = $args->getDataToBeTranslated();
        $message =
            '%class%::%prop% is a Bidirectional ManyToOne, it cannot be shared ' .
            'amongst translations. Either remove the @SharedAmongstTranslation ' .
            'annotation or choose another association type.';

        throw new ErrorException(
            strtr($message, [
                '%class%' => $data::class,
                '%prop%' => $args->getProperty()->name,
            ])
        );
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): null
    {
        return null;
    }

    public function translate(TranslationArgs $args): mixed
    {
        $clone = clone $args->getDataToBeTranslated();
        $parentFieldName = null;

        $fieldName = $args->getProperty()->name;
        $associations = $this->em->getClassMetadata($clone::class)->getAssociationMappings();

        foreach ($associations as $key => $association) {
            if ($fieldName === $key) {
                $parentFieldName = $association['fieldName'];
            }
        }

        if ($parentFieldName !== null) {
            $clone->setLocale($args->getTargetLocale());
            $this->propertyAccessor->setValue($clone, $parentFieldName, $args->getTranslatedParent());
            return $clone;
        }

        return $this->translator->translate($args->getDataToBeTranslated(), $args->getTargetLocale());
    }
}
