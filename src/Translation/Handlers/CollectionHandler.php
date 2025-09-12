<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToMany;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * Handles translation of Doctrine ManyToMany associations.
 */
final readonly class CollectionHandler implements TranslationHandlerInterface
{
    private readonly Closure $propertyAccessor;

    public function __construct(
        private AttributeHelper $attributeHelper,
        private EntityManagerInterface $em,
        private EntityTranslatorInterface $translator,
        callable|null $propertyAccessor = null,
    ) {
        // convert callable -> Closure or use default behaviour
        $this->propertyAccessor = $propertyAccessor !== null
            ? $propertyAccessor(...)
            : static function (ReflectionProperty $p, object $o) {
                // default behaviour: read the property value (may throw, caught by caller)
                return $p->getValue($o);
            };
    }

    public function supports(TranslationArgs $args): bool
    {
        $data = $args->getDataToBeTranslated();
        $prop = $args->getProperty();

        if (!$data instanceof Collection || $prop === null) {
            return false;
        }

        if (!$this->attributeHelper->isManyToMany($prop)) {
            return false;
        }

        $attributes = $prop->getAttributes(ManyToMany::class);
        if ($attributes === []) {
            return false;
        }

        $argsAttr = $attributes[0]->getArguments();
        return isset($argsAttr['mappedBy']) && $argsAttr['mappedBy'] !== null;
    }

    private function discoverProperty(object $owner, Collection $collection): ReflectionProperty|null
    {
        $refClass = new ReflectionClass($owner);

        foreach ($refClass->getProperties() as $prop) {
            try {
                $value = ($this->propertyAccessor)($prop, $owner);

                if ($value === $collection) {
                    return $prop;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function resolveMappedBy(ReflectionProperty $prop, object $owner): string|null
    {
        $attributes = $prop->getAttributes(ManyToMany::class);
        if ($attributes !== []) {
            $args = $attributes[0]->getArguments();
            if (isset($args['mappedBy'])) {
                return $args['mappedBy'];
            }
        }

        $meta = $this->em->getClassMetadata($owner::class);
        $assoc = $meta->getAssociationMapping($prop->getName());

        return $assoc['mappedBy'] ?? null;
    }

    public function handleSharedAmongstTranslations(TranslationArgs $args): Collection
    {
        $collection = $args->getDataToBeTranslated();
        if (!$collection instanceof Collection) {
            throw new RuntimeException('CollectionHandler::handleSharedAmongstTranslations expects a Collection.');
        }

        $newOwner = $args->getTranslatedParent();
        $prop = $args->getProperty() ?? ($newOwner ? $this->discoverProperty($newOwner, $collection) : null);

        if ($newOwner === null || $prop === null) {
            return new ArrayCollection();
        }

        $mappedBy = $this->resolveMappedBy($prop, $newOwner);
        if ($mappedBy === null) {
            throw new RuntimeException(sprintf(
                'Association mapping not found for property "%s" on "%s".',
                $prop->getName(),
                $newOwner::class
            ));
        }

        $newCollection = new ArrayCollection();

        foreach ($collection as $item) {
            $rp = new ReflectionProperty($item::class, $mappedBy);

            $subArgs = new TranslationArgs($item, $args->getSourceLocale(), $args->getTargetLocale());
            $subArgs->setTranslatedParent($newOwner)->setProperty($rp);

            $itemTrans = $this->translator->processTranslation($subArgs);

            $rp->setValue($itemTrans, new ArrayCollection([$newOwner]));
            $newCollection->add($itemTrans);
        }

        return $newCollection;
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): ArrayCollection
    {
        $collection = $args->getDataToBeTranslated();
        if (!$collection instanceof Collection) {
            return new ArrayCollection();
        }

        $newOwner = $args->getTranslatedParent();
        $prop = $args->getProperty() ?? ($newOwner ? $this->discoverProperty($newOwner, $collection) : null);

        if ($newOwner && $prop) {
            $prop->setValue($newOwner, new ArrayCollection());
        }

        return new ArrayCollection();
    }

    public function translate(TranslationArgs $args): Collection
    {
        $collection = $args->getDataToBeTranslated();
        if (!$collection instanceof Collection) {
            throw new RuntimeException('CollectionHandler::translate expects a Collection.');
        }

        $newOwner = $args->getTranslatedParent();
        $prop = $args->getProperty() ?? ($newOwner ? $this->discoverProperty($newOwner, $collection) : null);

        if ($newOwner === null || $prop === null) {
            return new ArrayCollection($collection->toArray());
        }

        $mappedBy = $this->resolveMappedBy($prop, $newOwner);
        if ($mappedBy === null) {
            throw new RuntimeException(sprintf(
                'Association "%s::%s" is not a bidirectional ManyToMany (missing mappedBy).',
                $newOwner::class,
                $prop->getName()
            ));
        }

        $newCollection = new ArrayCollection();

        foreach ($collection as $item) {
            $rp = new ReflectionProperty($item::class, $mappedBy);
            $rp->setValue($item, new ArrayCollection());

            $itemTrans = $this->translator->translate($item, $args->getTargetLocale());
            $rp->setValue($itemTrans, new ArrayCollection([$newOwner]));

            $newCollection->add($itemTrans);
        }

        return $newCollection;
    }
}
