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

    #[\Override]
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
     * @throws \RuntimeException
     */
    #[\Override]
    public function translate(TranslationContext $context): mixed
    {
        \assert($context instanceof EntityTranslationContext);

        $entity           = $context->getEntity();
        $property         = $context->getProperty();
        $translatedParent = $context->getTranslatedParent();

        // The back-reference form is told apart from the direct form by one fact:
        // $propertyName names an association declared on the entity's OWN class (the
        // child's ManyToOne pointing back at the parent), and BidirectionalOneToManyHandler
        // handed us the parent's clone to point it at. Resolved up front because the shared
        // branch below depends on it, not only the repair at the end.
        $propertyName        = $property?->name;
        $isBackReferenceForm = false;

        if (null !== $propertyName) {
            $associations        = $this->entityManager->getClassMetadata($entity::class)->getAssociationMappings();
            $isBackReferenceForm = isset($associations[$propertyName]) && null !== $translatedParent;
        }

        // Sharing a bidirectional ManyToOne is refused in the direct form only: the
        // association's target is itself translatable, so "the identical instance on every
        // locale" would leave the relation's ownership ambiguous across variants.
        //
        // In the back-reference form the SAME attribute means something else entirely. The
        // child was reached by walking the parent's OneToMany, so the property being
        // resolved is the child's FK back to that parent -- and the only correct value for
        // it on the child's clone is the parent's clone, which is exactly what the repair
        // at the end of this method writes for the non-shared case. Throwing here made the
        // parent untranslatable the moment a child declared its back-reference shared, for
        // an attribute that changes nothing about the outcome. (5.1)
        if ($context->isShared() && !$isBackReferenceForm) {
            $message = '%class%::%prop% is a Bidirectional ManyToOne, it cannot be shared '.
                'amongst translations. Either remove the @SharedAmongstTranslation '.
                'annotation or choose another association type.';

            throw new \RuntimeException(strtr($message, ['%class%' => $entity::class, '%prop%' => $propertyName ?? 'unknown']));
        }

        if ($context->isEmpty()) {
            return null;
        }

        if (null === $propertyName) {
            return $entity;
        }

        // TranslatableEntityHandler::translate() receives this very context object and
        // throws on the same flag for the same reason as the direct form above; the flag
        // has been resolved here, so it is cleared before delegating.
        $context->setShared(false);

        // Delegate the clone itself to the entity pipeline: translateProperties() over the
        // entity's own fields (shared/empty/translatable, not just the back-reference),
        // generated-id reset, and locale. A plain `clone $entity` here left all of that
        // undone -- including the id, which PHP's clone copies verbatim, so a flush
        // re-inserted the source's row under a fresh identity instead of ever reusing an
        // existing translation. Whether $entity already has a $targetLocale variant was
        // already settled before this handler ever ran -- EntityTranslator::processTranslation()
        // preloaded and cache-checked this same $context's subject first (see
        // TranslatableEntityHandler's own docblock) -- so there is nothing left to look up here.
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
        if ($isBackReferenceForm) {
            $this->propertyAccessor->setValue($translated, $propertyName, $translatedParent);
        }

        return $translated;
    }
}
