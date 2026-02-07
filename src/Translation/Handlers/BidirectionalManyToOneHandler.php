<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToOne;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * Translate a single entity which is on the many-side. If the related property is a translatable entity, translate that related entity
 * (by calling the translator). If there is no property or the entity is not translatable, return the entity (not a clone) --
 * or clone+set locale depending on your desired semantics (we'll choose safe/consistent behavior below).
 *
 * Final rule of thumb
 * If no translation work is possible -> return original.
 * If translation is happening -> return a clone with new locale.
 */
final readonly class BidirectionalManyToOneHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper $attributeHelper,
        private EntityManagerInterface $entityManager,
        private PropertyAccessorInterface $propertyAccessor,
        private EntityTranslatorInterface $translator,
    ) {
    }

    public function supports(TranslationArgs $args): bool
    {
        $entity = $args->getDataToBeTranslated();

        if (!$entity instanceof TranslatableInterface) {
            return false;
        }

        $property = $args->getProperty();
        if (null === $property || !$this->attributeHelper->isManyToOne($property)) {
            return false;
        }

        $attributes = $property->getAttributes(ManyToOne::class);
        if (0 === count($attributes)) {
            return false;
        }

        $arguments = $attributes[0]->getArguments();

        return isset($arguments['inversedBy']);
    }

    /**
     * @throws \ErrorException
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): mixed
    {
        $data     = $args->getDataToBeTranslated();
        $property = $args->getProperty();
        $message  = '%class%::%prop% is a Bidirectional ManyToOne, it cannot be shared '.
            'amongst translations. Either remove the @SharedAmongstTranslation '.
            'annotation or choose another association type.';

        throw new \ErrorException(strtr($message, [
            '%class%' => \is_object($data) ? $data::class : 'unknown',
            '%prop%'  => null !== $property ? $property->name : 'unknown',
        ]));
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

        $property = $args->getProperty();
        if (null === $property) {
            return $entity;
        }

        $propertyName = $property->name;
        $associations = $this->entityManager->getClassMetadata($entity::class)->getAssociationMappings();

        if (!isset($associations[$propertyName])) {
            return $entity;
        }

        $targetLocale = $args->getTargetLocale();

        // Clone so we don't mutate original; set the new locale.
        $clone = clone $entity;
        $clone->setLocale($targetLocale);

        $related = $this->propertyAccessor->getValue($entity, $propertyName);

        if ($related instanceof TranslatableInterface && \is_string($targetLocale)) {
            $translatedRelated = $this->translator->translate($related, $targetLocale);
            $this->propertyAccessor->setValue($clone, $propertyName, $translatedRelated);
        } else {
            $this->propertyAccessor->setValue($clone, $propertyName, $related);
        }

        return $clone;
    }
}
