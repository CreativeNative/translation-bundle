<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToOne;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Context\EntityTranslationContext;
use Tmi\TranslationBundle\Translation\Context\TranslationContext;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * Translates a ManyToOne association, in either of the two shapes it can be reached in:
 *
 * - The direct form: $context->getEntity() is the related (many-)one-side entity
 *   referenced through a property declared on a *different*, owning class (reached via
 *   DoctrineObjectHandler::translateProperties()). There is no scalar field on the related
 *   class to repair -- the related entity is simply translated to the matching locale
 *   (get-or-create) and takes the property's new value.
 * - The back-reference form: $context->getEntity() is the child entity itself,
 *   reached via BidirectionalOneToManyHandler with a property that names the child's own
 *   ManyToOne field pointing back at the parent. Here the field being processed *is* an
 *   association on the entity's own class, which is how the two forms are told apart below.
 */
final readonly class BidirectionalManyToOneHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper $attributeHelper,
        private EntityManagerInterface $entityManager,
        private PropertyAccessorInterface $propertyAccessor,
        private TranslatableEntityHandler $translatableEntityHandler,
    ) {
    }

    public function supports(TranslationContext $context): bool
    {
        if (!$context instanceof EntityTranslationContext) {
            return false;
        }

        $property = $context->getProperty();
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
    public function translate(TranslationContext $context): mixed
    {
        \assert($context instanceof EntityTranslationContext);

        if ($context->isShared()) {
            $property = $context->getProperty();
            $message  = '%class%::%prop% is a Bidirectional ManyToOne, it cannot be shared '.
                'amongst translations. Either remove the @SharedAmongstTranslation '.
                'annotation or choose another association type.';

            throw new \ErrorException(strtr($message, ['%class%' => $context->getEntity()::class, '%prop%' => null !== $property ? $property->name : 'unknown']));
        }

        if ($context->isEmpty()) {
            return null;
        }

        $entity   = $context->getEntity();
        $property = $context->getProperty();
        if (null === $property) {
            return $entity;
        }

        $propertyName = $property->name;
        $associations = $this->entityManager->getClassMetadata($entity::class)->getAssociationMappings();

        // Delegate the clone itself to the entity pipeline: existing-variant lookup via the
        // finder, translateProperties() over the entity's own fields (shared/empty/
        // translatable, not just the back-reference), generated-id reset, and locale. A plain
        // `clone $entity` here left all of that undone -- including the id, which PHP's clone
        // copies verbatim, so a flush re-inserted the source's row under a fresh identity
        // instead of ever reusing an existing translation.
        $translated = $this->translatableEntityHandler->translate($context);
        \assert($translated instanceof TranslatableInterface);

        // Back-reference form only: $propertyName names an association declared on the
        // entity's own class (reached via BidirectionalOneToManyHandler). The pipeline above
        // just ran translateProperties() over that very field, which still held the source
        // parent (a shallow copy) and got resolved through processTranslation() -- that
        // parent is still mid-translation at this point, so the in-progress guard in
        // EntityTranslator::processTranslation() caught the recursion and handed back the
        // untranslated source instead of infinitely recursing. Overwrite it with the parent
        // this handler already knows is being translated. The direct form (property declared
        // on a different, owning class) never matches here -- $translated is simply the
        // related entity translated to the matching locale, nothing left to repair.
        $translatedParent = $context->getTranslatedParent();
        if (isset($associations[$propertyName]) && null !== $translatedParent) {
            $this->propertyAccessor->setValue($translated, $propertyName, $translatedParent);
        }

        return $translated;
    }
}
