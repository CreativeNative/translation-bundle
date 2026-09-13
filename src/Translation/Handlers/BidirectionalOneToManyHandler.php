<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\InverseSideMapping;
use Doctrine\ORM\Mapping\OneToMany;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Exception\SharedAssociationException;
use Tmi\TranslationBundle\Translation\Context\EntityTranslationContext;
use Tmi\TranslationBundle\Translation\Context\PropertyTranslationContext;
use Tmi\TranslationBundle\Translation\Context\TranslationContext;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;
use Tmi\TranslationBundle\Utils\ReflectionHelper;

/**
 * Receives a collection (children). For each child:
 * If the child is translatable, ask the translator to process the child with a context that carries the mappedBy
 * ReflectionProperty and the translated parent. This is where the inverse-side fix-up (set child's parent to the translated parent) must
 * happen -- the OneToMany handler is the owner of the collection; it must set the child's parent property to the new translated parent.
 * If the child is not translatable, keep it as-is in the returned collection.
 *
 * Final rule of thumb
 * If we cannot translate (no parent or no property, or property not mapped) -> return the original collection.
 * If translation is possible -> build a new collection with translated children.
 *
 * Before iterating, the whole collection is handed to {@see EntityTranslatorInterface::preload()}
 * once ({@see CollectionTranslationSupport::preload()}); a child the cycle guard handed back
 * untranslated is skipped ({@see CollectionTranslationSupport::isCycleGuardFallback()}).
 */
final readonly class BidirectionalOneToManyHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper $attributeHelper,
        private EntityTranslatorInterface $translator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[\Override]
    public function supports(TranslationContext $context): bool
    {
        // The value of a OneToMany property is the children Collection, never the entity
        // itself -- guarding on TranslatableInterface here made supports() always false.
        if (!$context instanceof PropertyTranslationContext || !$context->getValue() instanceof Collection) {
            return false;
        }

        $property = $context->getProperty();
        if (null === $property || !$this->attributeHelper->isOneToMany($property)) {
            return false;
        }

        $attributes = $property->getAttributes(OneToMany::class);
        if (0 === \count($attributes)) {
            return false;
        }

        $arguments = $attributes[0]->getArguments();

        return isset($arguments['mappedBy']);
    }

    /**
     * @throws SharedAssociationException
     * @throws \ReflectionException
     *
     * @return Collection<int, mixed>
     */
    #[\Override]
    public function translate(TranslationContext $context): Collection
    {
        \assert($context instanceof PropertyTranslationContext);

        if ($context->isShared()) {
            $property = $context->getProperty();

            throw SharedAssociationException::forAssociation('bidirectional OneToMany', null !== $property ? $property->class : 'unknown', null !== $property ? $property->name : 'unknown');
        }

        if ($context->isEmpty()) {
            return new ArrayCollection();
        }

        $children = $context->getValue();
        \assert($children instanceof Collection);

        $translatedParent = $context->getTranslatedParent();
        $property         = $context->getProperty();

        // Guard: must have both property and translated parent
        if (null === $translatedParent || null === $property) {
            return $children; // nothing to translate -> return original
        }

        $associations = $this->entityManager->getClassMetadata($translatedParent::class)->getAssociationMappings();

        // Guard: property must exist in association mappings and have mappedBy
        $assocEntry = $associations[$property->name] ?? null;
        $mappedBy   = $assocEntry instanceof InverseSideMapping ? $assocEntry->mappedBy : null;
        if (!\is_string($mappedBy)) {
            return $children; // not a valid relation -> return original
        }

        $targetLocale = $context->getTargetLocale();
        CollectionTranslationSupport::preload($this->translator, $children, $targetLocale);

        $newCollection = new ArrayCollection();

        foreach ($children as $child) {
            if (!$child instanceof TranslatableInterface) {
                // child is not translatable -> just reuse
                $newCollection->add($child);
                continue;
            }

            // The child's context carries the parent's clone and names the child's own FK
            // field; the flag is what BidirectionalManyToOneHandler reads to know it is
            // repairing a back-reference and not translating a direct association.
            $subContext = new EntityTranslationContext($child, $context->getSourceLocale(), $context->getTargetLocale())
                ->setTranslatedParent($translatedParent)
                ->setProperty(ReflectionHelper::getProperty($child::class, $mappedBy))
                ->setBackReference(true);

            $translatedChild = $this->translator->processTranslation($subContext);

            if (CollectionTranslationSupport::isCycleGuardFallback($translatedChild, $child, $targetLocale)) {
                continue;
            }

            $newCollection->add($translatedChild);

            // keep bidirectional consistency
            if (\is_object($translatedChild)) {
                $childProperty = ReflectionHelper::getProperty($translatedChild::class, $mappedBy);
                $childProperty->setValue($translatedChild, $translatedParent);
            }
        }

        return $newCollection;
    }
}
