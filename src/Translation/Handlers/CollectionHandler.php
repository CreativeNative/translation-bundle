<?php

declare(strict_types=1);

namespace TMI\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToMany;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslatorInterface;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * Handles bidirectional ManyToMany collections.
 *
 * Responsibilities:
 *  - supports(): returns true for Collection properties annotated with #[ManyToMany(...)]
 *    where 'mappedBy' is present (i.e. the collection is the inverse side).
 *  - handleSharedAmongstTranslations(): clone collection items and ensure inverse side
 *    on translated items points back to the new owner (by setting the mappedBy collection
 *    to a new ArrayCollection containing $newOwner).
 *  - handleEmptyOnTranslate(): returns an empty collection.
 *  - translate(): translates each item (via translator->translate) and sets the inverse
 *    mappedBy collection of the translated item to a single-element collection containing
 *    $newOwner.
 */
final readonly class CollectionHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper           $attributeHelper,
        private EntityManagerInterface    $em,
        private EntityTranslatorInterface $translator,
    ) {
    }

    public function supports(TranslationArgs $args): bool
    {
        $data = $args->getDataToBeTranslated();
        $prop = $args->getProperty();

        if (!$data instanceof Collection) {
            return false;
        }

        if ($prop === null) {
            return false;
        }

        // AttributeHelper must say it's a ManyToMany
        if (!$this->attributeHelper->isManyToMany($prop)) {
            return false;
        }

        $attributes = $prop->getAttributes(ManyToMany::class);
        if ($attributes === []) {
            // No ManyToMany attribute on the property
            return false;
        }

        $arguments = $attributes[0]->getArguments();

        // We consider this handler for the inverse side: mappedBy must exist
        return array_key_exists('mappedBy', $arguments) && $arguments['mappedBy'] !== null;
    }

    /**
     * Translate a collection that is shared amongst translations (inverse side).
     *
     * This will:
     *  - clone the collection,
     *  - for each item: process translation (via translator->processTranslation),
     *  - set the mappedBy property on the translated item to a new ArrayCollection([$newOwner]),
     *  - return the new collection.
     *
     * @throws ReflectionException
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): Collection
    {
        $collection = $args->getDataToBeTranslated();
        if (!$collection instanceof Collection) {
            throw new RuntimeException('CollectionHandler::handleSharedAmongstTranslations expects a Collection.');
        }

        $newCollection = clone $collection;

        $newOwner = $args->getTranslatedParent();
        $prop = $args->getProperty();
        if ($newOwner === null || $prop === null) {
            // Defensive: empty collection if owner/property not provided
            return new ArrayCollection();
        }

        // find mapping for owning side (we need mappedBy)
        $meta = $this->em->getClassMetadata($newOwner::class);
        $mappings = $meta->getAssociationMappings();
        $assoc = $mappings[$prop->name] ?? null;
        if ($assoc === null) {
            throw new RuntimeException(sprintf(
                'CollectionHandler::handleSharedAmongstTranslations: Association mapping not found for property "%s" on "%s".',
                $prop->name,
                $newOwner::class
            ));
        }
        $mappedBy = $assoc['mappedBy'] ?? null;
        if ($mappedBy === null) {
            throw new RuntimeException(sprintf(
                'CollectionHandler::handleSharedAmongstTranslations: Association "%s::%s" has no "mappedBy" (not inverse side).',
                $newOwner::class,
                $prop->name
            ));
        }

        foreach ($newCollection as $key => $item) {
            // reflect the mappedBy property on the child
            $rp = new ReflectionProperty($item::class, $mappedBy);
            $subArgs = new TranslationArgs($item, $args->getSourceLocale(), $args->getTargetLocale())
                ->setTranslatedParent($newOwner)
                ->setProperty($rp);

            // allow translator to use handler chain (processTranslation) — translator may be the real EntityTranslator
            $itemTrans = $this->translator->processTranslation($subArgs);

            // set inverse side: mappedBy -> ArrayCollection([$newOwner])
            $rp->setValue($itemTrans, new ArrayCollection([$newOwner]));

            $newCollection[$key] = $itemTrans;
        }

        return $newCollection;
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): ArrayCollection
    {
        return new ArrayCollection([]);
    }

    /**
     * Translate a collection (owning side).
     *
     * For each item:
     *  - set the child->mappedBy to an empty collection,
     *  - call translator->translate(item, targetLocale),
     *  - set the itemTrans->mappedBy to new ArrayCollection([$newOwner]),
     *  - add to new collection.
     *
     * @throws ReflectionException
     */
    public function translate(TranslationArgs $args): Collection
    {
        $collection = $args->getDataToBeTranslated();
        if (!$collection instanceof Collection) {
            throw new RuntimeException('CollectionHandler::translate expects a Collection.');
        }

        $newCollection = clone $collection;

        $newOwner = $args->getTranslatedParent();
        $prop = $args->getProperty();
        if ($newOwner === null || $prop === null) {
            // Defensive: empty collection when insufficient context
            return new ArrayCollection();
        }

        $meta = $this->em->getClassMetadata($newOwner::class);
        $mappings = $meta->getAssociationMappings();
        $assoc = $mappings[$prop->name] ?? null;
        if ($assoc === null) {
            throw new RuntimeException(sprintf(
                'CollectionHandler::translate: Association mapping not found for property "%s" on "%s".',
                $prop->name,
                $newOwner::class
            ));
        }

        $mappedBy = $assoc['mappedBy'] ?? null;
        if ($mappedBy === null) {
            throw new RuntimeException(sprintf(
                'CollectionHandler::translate: Association "%s::%s" is not a bidirectional ManyToMany (missing mappedBy).',
                $newOwner::class,
                $prop->name
            ));
        }

        foreach ($newCollection as $key => $item) {
            $rp = new ReflectionProperty($item::class, $mappedBy);

            // before translating, clear the child's pointer back to the owner
            $rp->setValue($item, new ArrayCollection([]));

            $itemTrans = $this->translator->translate($item, $args->getTargetLocale());

            // set inverse side on the translated item
            $rp->setValue($itemTrans, new ArrayCollection([$newOwner]));

            $newCollection[$key] = $itemTrans;
        }

        return $newCollection;
    }
}
