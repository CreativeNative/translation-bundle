<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\InverseSideMapping;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\MappingException;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;

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

    public function supports(TranslationArgs $args): bool
    {
        $entity = $args->getDataToBeTranslated();

        if (!$entity instanceof TranslatableInterface) {
            return false;
        }

        $property = $args->getProperty();
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
     * SharedAmongstTranslations is not supported for bidirectional ManyToMany collections.
     *
     * If no property is provided in the args, return the collection unchanged (caller may handle fallbacks).
     *
     * @throws \ReflectionException|MappingException
     *
     * @return Collection<int, mixed>
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): Collection
    {
        $collection = $args->getDataToBeTranslated();
        if (!$collection instanceof Collection) {
            throw new \RuntimeException('CollectionHandler::handleSharedAmongstTranslations expects a Collection.');
        }

        $prop = $args->getProperty();
        if (null === $prop) {
            return $collection;
        }

        // Check for SharedAmongstTranslations attribute
        $sharedAttrs = $prop->getAttributes(SharedAmongstTranslations::class);
        if (count($sharedAttrs) > 0) {
            $owner      = $args->getTranslatedParent();
            $ownerClass = \is_object($owner) ? $owner::class : $prop->getDeclaringClass()->getName();

            throw new \RuntimeException(sprintf('SharedAmongstTranslations is not allowed on bidirectional ManyToMany associations. Property "%s" of class "%s" is invalid.', $prop->getName(), $ownerClass));
        }

        // If we reach here, no shared attribute exists - proceed with normal translation
        return $this->translate($args);
    }

    /**
     * Clear the target collection on the translated parent (EmptyOnTranslate behaviour).
     *
     * @return Collection<int, mixed>
     */
    public function handleEmptyOnTranslate(TranslationArgs $args): Collection
    {
        $collection = $args->getDataToBeTranslated();

        if (!$collection instanceof Collection) {
            return new ArrayCollection();
        }

        $newOwner = $args->getTranslatedParent();

        $prop = $args->getProperty() ?? (\is_object($newOwner) ? $this->discoverProperty($newOwner, $collection) : null);

        if (\is_object($newOwner) && null !== $prop) {
            try {
                $prop->setValue($newOwner, new ArrayCollection());
            } catch (\Throwable) {
                // best-effort: swallow exceptions - handler must not break translation pipeline
            }
        }

        return new ArrayCollection();
    }

    /**
     * @throws \ReflectionException
     * @throws MappingException
     *
     * @return Collection<int, mixed>
     */
    public function translate(TranslationArgs $args): Collection
    {
        $collection = $args->getDataToBeTranslated();
        if (!$collection instanceof Collection) {
            throw new \RuntimeException('CollectionHandler::translate expects a Collection.');
        }

        $newOwner = $args->getTranslatedParent();
        $prop     = $args->getProperty() ?? (\is_object($newOwner) ? $this->discoverProperty($newOwner, $collection) : null);

        if (!\is_object($newOwner) || null === $prop) {
            return new ArrayCollection($collection->toArray());
        }

        $mappedBy = $this->resolveMappedBy($prop, $newOwner);
        if (null === $mappedBy) {
            throw new \RuntimeException(sprintf('Association "%s::%s" is not a bidirectional ManyToMany (missing mappedBy).', $newOwner::class, $prop->getName()));
        }

        $newCollection = new ArrayCollection();
        $targetLocale  = $args->getTargetLocale();

        foreach ($collection as $item) {
            if (!$item instanceof TranslatableInterface || !\is_string($targetLocale)) {
                $newCollection->add($item);
                continue;
            }

            $rp = new \ReflectionProperty($item::class, $mappedBy);
            $rp->setValue($item, new ArrayCollection());

            $itemTrans = $this->translator->translate($item, $targetLocale);
            $rpTrans   = new \ReflectionProperty($itemTrans::class, $mappedBy);
            $rpTrans->setValue($itemTrans, new ArrayCollection([$newOwner]));

            $newCollection->add($itemTrans);
        }

        return $newCollection;
    }

    /**
     * @param Collection<int, mixed> $collection
     */
    private function discoverProperty(object $owner, Collection $collection): \ReflectionProperty|null
    {
        $refClass = new \ReflectionClass($owner);

        foreach ($refClass->getProperties() as $prop) {
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
     * @throws MappingException
     */
    private function resolveMappedBy(\ReflectionProperty $prop, object $owner): string|null
    {
        $attributes = $prop->getAttributes(ManyToMany::class);
        if ([] !== $attributes) {
            $attrArgs = $attributes[0]->getArguments();
            if (isset($attrArgs['mappedBy']) && \is_string($attrArgs['mappedBy'])) {
                return $attrArgs['mappedBy'];
            }
        }

        $meta  = $this->entityManager->getClassMetadata($owner::class);
        $assoc = $meta->getAssociationMapping($prop->getName());

        return $assoc instanceof InverseSideMapping ? $assoc->mappedBy : null;
    }
}
