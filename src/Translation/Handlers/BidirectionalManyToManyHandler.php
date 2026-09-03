<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\InverseSideMapping;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Mapping\OwningSideMapping;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Context\PropertyTranslationContext;
use Tmi\TranslationBundle\Translation\Context\TranslationContext;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;
use Tmi\TranslationBundle\Utils\ReflectionHelper;

/**
 * Handles translation of Doctrine ManyToMany associations.
 */
final readonly class BidirectionalManyToManyHandler implements TranslationHandlerInterface
{
    private \Closure $propertyAccessor;

    public function __construct(
        private AttributeHelper $attributeHelper,
        private EntityManagerInterface $entityManager,
        private EntityTranslatorInterface $translator,
        callable|null $propertyAccessor = null,
    ) {
        // convert callable -> Closure or use default behaviour
        $this->propertyAccessor = null !== $propertyAccessor
            ? \Closure::fromCallable($propertyAccessor)
            : (static fn (\ReflectionProperty $p, object $o): mixed => $p->getValue($o));
    }

    public function supports(TranslationContext $context): bool
    {
        // The value of a ManyToMany property is the Collection, never the entity itself --
        // guarding on TranslatableInterface here made supports() always false.
        if (!$context instanceof PropertyTranslationContext || !$context->getValue() instanceof Collection) {
            return false;
        }

        $property = $context->getProperty();
        if (null === $property || !$this->attributeHelper->isManyToMany($property)) {
            return false;
        }

        $attributes = $property->getAttributes(ManyToMany::class);
        if ([] === $attributes) {
            return false;
        }

        $arguments = $attributes[0]->getArguments();

        // Bidirectional ManyToMany: either mappedBy or inversedBy must exist
        return isset($arguments['mappedBy']) || isset($arguments['inversedBy']);
    }

    /**
     * $context->isShared(): SharedAmongstTranslations is not supported for bidirectional
     * ManyToMany collections -- throws, unless the property turns out not to actually
     * carry the attribute (defensive; the translator only sets isShared() when it does).
     *
     * $context->isEmpty(): clears the target collection on the translated parent
     * (best-effort) and returns a fresh empty collection.
     *
     * Otherwise: translates each item through the full entity pipeline.
     *
     * @throws \ReflectionException
     * @throws MappingException
     *
     * @return Collection<int, mixed>
     */
    public function translate(TranslationContext $context): Collection
    {
        \assert($context instanceof PropertyTranslationContext);
        $collection = $context->getValue();

        if ($context->isShared()) {
            if (!$collection instanceof Collection) {
                throw new \RuntimeException('BidirectionalManyToManyHandler::translate() expects a Collection.');
            }

            $prop = $context->getProperty();
            if (null === $prop) {
                return $collection;
            }

            // Check for SharedAmongstTranslations attribute
            $sharedAttrs = $prop->getAttributes(SharedAmongstTranslations::class);
            if (count($sharedAttrs) > 0) {
                $owner      = $context->getTranslatedParent();
                $ownerClass = null !== $owner ? $owner::class : $prop->getDeclaringClass()->getName();

                throw new \RuntimeException(sprintf('SharedAmongstTranslations is not allowed on bidirectional ManyToMany associations. Property "%s" of class "%s" is invalid.', $prop->getName(), $ownerClass));
            }

            // If we reach here, no shared attribute exists - proceed with normal translation
            return $this->translateCollection($context);
        }

        if ($context->isEmpty()) {
            if (!$collection instanceof Collection) {
                return new ArrayCollection();
            }

            $newOwner = $context->getTranslatedParent();

            $prop = $context->getProperty() ?? (null !== $newOwner ? $this->discoverProperty($newOwner, $collection) : null);

            if (null !== $newOwner && null !== $prop) {
                try {
                    $prop->setValue($newOwner, new ArrayCollection());
                } catch (\Throwable) {
                    // best-effort: swallow exceptions - handler must not break translation pipeline
                }
            }

            return new ArrayCollection();
        }

        return $this->translateCollection($context);
    }

    /**
     * The whole collection is handed to {@see EntityTranslatorInterface::preload()} once,
     * before the loop below: one batched lookup query per item class rather than one per
     * item, since each item is otherwise its own translate() call with its own internal
     * single-entity preload().
     *
     * @throws \ReflectionException
     * @throws MappingException
     *
     * @return Collection<int, mixed>
     */
    private function translateCollection(PropertyTranslationContext $context): Collection
    {
        $collection = $context->getValue();
        if (!$collection instanceof Collection) {
            throw new \RuntimeException('BidirectionalManyToManyHandler::translate() expects a Collection.');
        }

        $newOwner = $context->getTranslatedParent();
        $prop     = $context->getProperty() ?? (null !== $newOwner ? $this->discoverProperty($newOwner, $collection) : null);

        if (null === $newOwner || null === $prop) {
            return new ArrayCollection($collection->toArray());
        }

        $mappedBy = $this->resolveBackReferenceField($prop, $newOwner);
        if (null === $mappedBy) {
            throw new \RuntimeException(sprintf('Association "%s::%s" is not a bidirectional ManyToMany (neither mappedBy nor inversedBy).', $newOwner::class, $prop->getName()));
        }

        $newCollection = new ArrayCollection();
        $targetLocale  = $context->getTargetLocale();

        // One batched lookup for the whole collection instead of leaving each item's own
        // translate() call to query for itself: preload() groups translatable items by
        // class and issues one LocaleVariantFinder query per class, ignoring
        // non-translatable items and anything already cached. A collection of K
        // translatable items of one class then costs one query total here, not K.
        if (\is_string($targetLocale)) {
            $this->translator->preload($collection, $targetLocale);
        }

        foreach ($collection as $item) {
            if (!$item instanceof TranslatableInterface || !\is_string($targetLocale)) {
                $newCollection->add($item);
                continue;
            }

            // Detach the back-reference for the duration of the translation only. It stops
            // the recursion from walking back into $newOwner's own collection (which would
            // rewrite the SOURCE parent), and it leaves the clone with an empty collection of
            // its own. The source item gets its collection back either way.
            $rp            = ReflectionHelper::getProperty($item::class, $mappedBy);
            $sourceBackRef = $rp->getValue($item);
            $rp->setValue($item, new ArrayCollection());

            try {
                $itemTrans = $this->translator->translate($item, $targetLocale);
            } finally {
                $rp->setValue($item, $sourceBackRef);
            }

            self::addBackReference($itemTrans, $mappedBy, $newOwner);

            $newCollection->add($itemTrans);
        }

        return $newCollection;
    }

    /**
     * Points the translated item back at the translated owner.
     *
     * Adds rather than replaces: when translate() hands back an already existing translation
     * instead of a fresh clone, that instance keeps whatever other owners it legitimately has.
     */
    private static function addBackReference(object $item, string $field, object $newOwner): void
    {
        $property = ReflectionHelper::getProperty($item::class, $field);
        $backRef  = $property->getValue($item);

        if (!$backRef instanceof Collection) {
            $property->setValue($item, new ArrayCollection([$newOwner]));

            return;
        }

        if (!$backRef->contains($newOwner)) {
            $backRef->add($newOwner);
        }
    }

    /**
     * @param Collection<int, mixed> $collection
     */
    private function discoverProperty(object $owner, Collection $collection): \ReflectionProperty|null
    {
        $refClass = new \ReflectionClass($owner);

        foreach (ReflectionHelper::getHierarchyProperties($refClass) as $prop) {
            try {
                $value = ($this->propertyAccessor)($prop, $owner);
            } catch (\Throwable) {
                // inaccessible or accessor failed - skip this property
                continue;
            }

            if ($value === $collection) {
                return $prop;
            }
        }

        return null;
    }

    /**
     * Resolves the name of the field on the RELATED class that points back at $owner.
     *
     * Which side of the association the translated entity sits on decides where that name
     * comes from: on the inverse side it is `mappedBy`, on the owning side `inversedBy`.
     * Both are valid bidirectional mappings -- only a genuinely unidirectional association
     * yields neither.
     *
     * @throws MappingException
     */
    private function resolveBackReferenceField(\ReflectionProperty $prop, object $owner): string|null
    {
        $attributes = $prop->getAttributes(ManyToMany::class);
        if ([] !== $attributes) {
            $attrArgs = $attributes[0]->getArguments();

            foreach (['mappedBy', 'inversedBy'] as $key) {
                if (isset($attrArgs[$key]) && \is_string($attrArgs[$key])) {
                    return $attrArgs[$key];
                }
            }
        }

        $meta  = $this->entityManager->getClassMetadata($owner::class);
        $assoc = $meta->getAssociationMapping($prop->getName());

        if ($assoc instanceof InverseSideMapping) {
            return $assoc->mappedBy;
        }

        return $assoc instanceof OwningSideMapping ? $assoc->inversedBy : null;
    }
}
