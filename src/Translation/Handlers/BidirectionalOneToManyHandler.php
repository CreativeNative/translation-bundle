<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\InverseSideMapping;
use Doctrine\ORM\Mapping\OneToMany;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
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
 * once: one batched lookup query per child class rather than one per child, since each
 * child is otherwise its own translate() call with its own internal single-entity preload().
 */
final readonly class BidirectionalOneToManyHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper $attributeHelper,
        private EntityTranslatorInterface $translator,
        private EntityManagerInterface $entityManager,
    ) {
    }

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
        if (0 === count($attributes)) {
            return false;
        }

        $arguments = $attributes[0]->getArguments();

        return isset($arguments['mappedBy']);
    }

    /**
     * @throws \ErrorException
     * @throws \ReflectionException
     *
     * @return Collection<int, mixed>
     */
    public function translate(TranslationContext $context): Collection
    {
        \assert($context instanceof PropertyTranslationContext);

        if ($context->isShared()) {
            $data     = $context->getValue();
            $property = $context->getProperty();
            $message  = '%class%::%prop% is a Bidirectional OneToMany, it cannot be shared '.
                'amongst translations. Either remove the SharedAmongstTranslation '.
                'attribute or choose another association type.';

            throw new \ErrorException(strtr($message, ['%class%' => \is_object($data) ? $data::class : 'unknown', '%prop%' => null !== $property ? $property->name : 'unknown']));
        }

        if ($context->isEmpty()) {
            return new ArrayCollection();
        }

        $children = $context->getValue();
        assert($children instanceof Collection);

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

        // One batched lookup for the whole collection instead of leaving each child's
        // own translate() call to query for itself: preload() groups translatable
        // children by class and issues one LocaleVariantFinder query per class,
        // ignoring non-translatable items and anything already cached. A collection of
        // K translatable children of one class then costs one query total here, not K.
        $targetLocale = $context->getTargetLocale();
        if (\is_string($targetLocale)) {
            $this->translator->preload($children, $targetLocale);
        }

        $newCollection = new ArrayCollection();

        foreach ($children as $child) {
            if (!$child instanceof TranslatableInterface) {
                // child is not translatable -> just reuse
                $newCollection->add($child);
                continue;
            }

            $subContext = new EntityTranslationContext($child, $context->getSourceLocale(), $context->getTargetLocale())
                ->setTranslatedParent($translatedParent)
                ->setProperty(ReflectionHelper::getProperty($child::class, $mappedBy));

            $translatedChild = $this->translator->processTranslation($subContext);
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
