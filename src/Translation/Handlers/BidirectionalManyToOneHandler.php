<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToOne;
use ErrorException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * Handles translation of ManyToOne relations.
 */
final readonly class BidirectionalManyToOneHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper $attributeHelper,
        private EntityManagerInterface $em,
        private PropertyAccessorInterface $propertyAccessor,
        private EntityTranslatorInterface $translator
    ) {
    }

    public function supports(TranslationArgs $args): bool
    {
        $entity = $args->getDataToBeTranslated();

        if (!$entity instanceof TranslatableInterface) {
            return false;
        }

        $property = $args->getProperty();
        if (!$property || !$this->attributeHelper->isManyToOne($property)) {
            return false;
        }

        $attributes = $property->getAttributes(ManyToOne::class);
        if (empty($attributes)) {
            return false;
        }

        $arguments = $attributes[0]->getArguments();

        return isset($arguments['inversedBy']);
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
        $entity = $args->getDataToBeTranslated();

        if (!$entity instanceof TranslatableInterface) {
            return $entity;
        }

        $clone = clone $entity;
        $clone->setLocale($args->getTargetLocale());

        $property = $args->getProperty();
        if (!$property) {
            return $clone;
        }

        $propertyName = $property->name;
        $associations = $this->em->getClassMetadata($clone::class)->getAssociationMappings();

        if (isset($associations[$propertyName])) {
            $related = $this->propertyAccessor->getValue($entity, $propertyName);

            if (null !== $args->getTranslatedParent()) {
                $this->propertyAccessor->setValue($clone, $propertyName, $args->getTranslatedParent());
            } elseif ($related instanceof TranslatableInterface) {
                $translatedRelated = $this->translator->translate($related, $args->getTargetLocale());
                $this->propertyAccessor->setValue($clone, $propertyName, $translatedRelated);
            } else {
                $this->propertyAccessor->setValue($clone, $propertyName, $related);
            }
        }
        return $clone;
    }
}
