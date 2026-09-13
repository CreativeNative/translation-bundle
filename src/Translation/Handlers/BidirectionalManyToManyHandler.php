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
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Exception\SharedAssociationException;
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
    /** @var \Closure(\ReflectionProperty, object): mixed */
    private \Closure $propertyAccessor;

    /**
     * @param (callable(\ReflectionProperty, object): mixed)|null $propertyAccessor
     */
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

    #[\Override]
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
     * $context->isShared(): the target is itself translatable, so sharing is refused.
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
    #[\Override]
    public function translate(TranslationContext $context): Collection
    {
        \assert($context instanceof PropertyTranslationContext);
        $collection = $context->getValue();

        if ($context->isShared()) {
            $prop  = $context->getProperty();
            $owner = $context->getTranslatedParent();

            throw SharedAssociationException::forAssociation('bidirectional ManyToMany', null !== $owner ? $owner::class : (null !== $prop ? $prop->class : 'unknown'), null !== $prop ? $prop->name : 'unknown');
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
            throw new \RuntimeException(\sprintf('Association "%s::%s" is not a bidirectional ManyToMany (neither mappedBy nor inversedBy).', $newOwner::class, $prop->getName()));
        }

        $newCollection = new ArrayCollection();
        $targetLocale  = $context->getTargetLocale();

        CollectionTranslationSupport::preload($this->translator, $collection, $targetLocale);

        foreach ($collection as $item) {
            if (!$item instanceof TranslatableInterface || !\is_string($targetLocale)) {
                $newCollection->add($item);
                continue;
            }

            $itemTrans = $this->translateItem($item, $mappedBy, $newOwner, $targetLocale);

            if (null !== $itemTrans) {
                $newCollection->add($itemTrans);
            }
        }

        return $newCollection;
    }

    /**
     * One item: translated through the entity pipeline with its back-reference detached
     * for the duration, then pointed at the translated owner. Null when the cycle guard
     * handed the source item back -- the detach only protects against $item's own
     * back-reference walking straight back into $newOwner's collection; a second,
     * independent path through the graph can still leave $item's tuuid in progress, and
     * addBackReference() would then write to the SOURCE item's field, whose owning side
     * is a persisted join row.
     *
     * @throws \ReflectionException
     */
    private function translateItem(TranslatableInterface $item, string $mappedBy, object $newOwner, string $targetLocale): TranslatableInterface|null
    {
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

        if (CollectionTranslationSupport::isCycleGuardFallback($itemTrans, $item, $targetLocale)) {
            return null;
        }

        self::addBackReference($itemTrans, $mappedBy, $newOwner);

        return $itemTrans;
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
