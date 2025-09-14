<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\OneToMany;
use ErrorException;
use ReflectionException;
use ReflectionProperty;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\EntityTranslator;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * Receives a collection (children). For each child:
 * If the child is translatable, ask the translator to process the child with a TranslationArgs that contains the mappedBy
 * ReflectionProperty and the translated parent. This is where the inverse-side fix-up (set child's parent to the translated parent) must
 * happen — the OneToMany handler is the owner of the collection; it must set the child's parent property to the new translated parent.
 * If the child is not translatable, keep it as-is in the returned collection.
 *
 * Final rule of thumb
 * If we cannot translate (no parent or no property, or property not mapped) → return the original collection.
 * If translation is possible → build a new collection with translated children.
 */
final readonly class BidirectionalOneToManyHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper $attributeHelper,
        private EntityTranslatorInterface $translator,
        private EntityManagerInterface $em
    ) {
    }

    public function supports(TranslationArgs $args): bool
    {
        $entity = $args->getDataToBeTranslated();

        if (!$entity instanceof TranslatableInterface) {
            return false;
        }

        $property = $args->getProperty();
        if (!$property || !$this->attributeHelper->isOneToMany($property)) {
            return false;
        }

        $attributes = $property->getAttributes(OneToMany::class);
        if (empty($attributes)) {
            return false;
        }

        $arguments = $attributes[0]->getArguments();

        return isset($arguments['mappedBy']);
    }

    /**
     * @throws ErrorException
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): mixed
    {
        $data = $args->getDataToBeTranslated();
        $message =
            '%class%::%prop% is a Bidirectional OneToMany, it cannot be shared ' .
            'amongst translations. Either remove the SharedAmongstTranslation ' .
            'attribute or choose another association type.';

        throw new ErrorException(
            strtr($message, [
                '%class%' => $data::class,
                '%prop%' => $args->getProperty()->name,
            ])
        );
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): ArrayCollection
    {
        return new ArrayCollection();
    }

    /**
     * @throws ReflectionException
     */
    public function translate(TranslationArgs $args): Collection
    {
        $children = $args->getDataToBeTranslated();
        assert($children instanceof Collection);

        $translatedParent = $args->getTranslatedParent();
        $property = $args->getProperty();

        // Guard: must have both property and translated parent
        if (!$translatedParent || !$property) {
            return $children; // nothing to translate → return original
        }

        $associations = $this->em->getClassMetadata($translatedParent::class)->getAssociationMappings();

        // Guard: property must exist in association mappings and have mappedBy
        if (!isset($associations[$property->name]['mappedBy'])) {
            return $children; // not a valid relation → return original
        }

        $mappedBy = $associations[$property->name]['mappedBy'];
        $newCollection = new ArrayCollection();

        foreach ($children as $child) {
            if (!$child instanceof TranslatableInterface) {
                // child is not translatable → just reuse
                $newCollection->add($child);
                continue;
            }

            $subArgs = new TranslationArgs($child, $args->getSourceLocale(), $args->getTargetLocale())
                ->setTranslatedParent($translatedParent)
                ->setProperty(new ReflectionProperty($child::class, $mappedBy));

            assert($this->translator instanceof EntityTranslator);
            $translatedChild = $this->translator->processTranslation($subArgs);
            $newCollection->add($translatedChild);

            // keep bidirectional consistency
            $childProperty = new ReflectionProperty($translatedChild::class, $mappedBy);
            $childProperty->setValue($translatedChild, $translatedParent);
        }

        return $newCollection;
    }
}
