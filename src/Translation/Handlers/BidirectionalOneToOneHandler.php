<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\InverseSideMapping;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\Mapping\OwningSideMapping;
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
        private EntityManagerInterface $entityManager,
        private PropertyAccessor $propertyAccessor,
        private AttributeHelper $attributeHelper,
        private TranslatableEntityHandler $translatableEntityHandler,
    ) {
    }

    public function supports(TranslationArgs $args): bool
    {
        $entity = $args->getDataToBeTranslated();

        if (!$entity instanceof TranslatableInterface) {
            return false;
        }

        $property = $args->getProperty();
        if (null === $property || !$this->attributeHelper->isOneToOne($property)) {
            return false;
        }

        $attributes = $property->getAttributes(OneToOne::class);
        if (0 === count($attributes)) {
            return false;
        }

        $arguments = $attributes[0]->getArguments();

        // With OneToOne, there can be mappedBy or inversedBy
        return (isset($arguments['mappedBy'])) || (isset($arguments['inversedBy']));
    }

    /**
     * @throws \ErrorException
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): mixed
    {
        $property = $args->getProperty();
        if (null !== $property && $this->attributeHelper->isOneToOne($property)) {
            $data    = $args->getDataToBeTranslated();
            $message = '%class%::%prop% is a Bidirectional OneToOne, it cannot be shared '.
                'amongst translations. Either remove the @SharedAmongstTranslation '.
                'annotation or choose another association type.';

            throw new \ErrorException(strtr($message, ['%class%' => \is_object($data) ? $data::class : 'unknown', '%prop%' => $property->name]));
        }

        return $args->getDataToBeTranslated();
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): null
    {
        return null;
    }

    public function translate(TranslationArgs $args): mixed
    {
        $data = $args->getDataToBeTranslated();
        assert($data instanceof TranslatableInterface);

        $property = $args->getProperty();
        assert(null !== $property);

        // Delegate the clone itself to the entity pipeline: existing-variant lookup via the
        // finder, translateProperties() over the related entity's own fields (shared/empty/
        // translatable, not just the back-reference), generated-id reset, and locale. A plain
        // `clone $data` here left all of that undone -- including the id, which PHP's clone
        // copies verbatim, so a flush re-inserted the source's row under a fresh identity
        // instead of ever reusing an existing translation.
        $translated = $this->translatableEntityHandler->translate($args);

        $fieldName       = $property->name;
        $associations    = $this->entityManager->getClassMetadata($translated::class)->getAssociationMappings();
        $parentFieldName = null;

        // Find the field on the related class that points back at the parent. Which side
        // the parent's field sits on decides how the back-reference is declared here: if
        // the parent is the inverse side, this side owns the relation and names the
        // parent's field in `inversedBy`; if the parent owns it, this side is the inverse
        // and names it in `mappedBy`. Both are valid bidirectional mappings.
        foreach ($associations as $association) {
            if ($association instanceof OwningSideMapping && $fieldName === $association->inversedBy) {
                $parentFieldName = $association->fieldName;
                break;
            }

            if ($association instanceof InverseSideMapping && $fieldName === $association->mappedBy) {
                $parentFieldName = $association->fieldName;
                break;
            }
        }

        // The entity pipeline above just ran translateProperties() over $translated's own
        // properties, including this very back-reference field -- which still held the source
        // parent (a shallow copy) and got resolved through processTranslation(). That parent is
        // still mid-translation at this point (its own translate() call is what led here), so
        // the in-progress guard in EntityTranslator::processTranslation() caught the recursion
        // and handed back the untranslated source instead of infinitely recursing. Overwrite it
        // with the parent this handler already knows is being translated.
        $translatedParent = $args->getTranslatedParent();
        if (\is_string($parentFieldName) && \is_object($translatedParent)) {
            $this->propertyAccessor->setValue($translated, $parentFieldName, $translatedParent);
        }

        return $translated;
    }
}
