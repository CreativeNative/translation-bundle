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

        if (!$this->attributeHelper->isManyToMany($prop)) {
            return false;
        }

        $attributes = $prop->getAttributes(ManyToMany::class);
        if ($attributes === []) {
            return false;
        }

        $arguments = $attributes[0]->getArguments();

        return array_key_exists('mappedBy', $arguments) && $arguments['mappedBy'] !== null;
    }

    /**
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
            return $newCollection;
        }

        $meta = $this->em->getClassMetadata($newOwner::class);
        $mappings = $meta->getAssociationMappings();
        $assoc = $mappings[$prop->name] ?? null;
        if ($assoc === null) {
            throw new RuntimeException(sprintf(
                'Association mapping not found for property "%s" on "%s".',
                $prop->name,
                $newOwner::class
            ));
        }

        $mappedBy = $assoc['mappedBy'] ?? null;
        if ($mappedBy === null) {
            throw new RuntimeException(sprintf(
                'Association "%s::%s" has no "mappedBy" (not inverse side).',
                $newOwner::class,
                $prop->name
            ));
        }

        foreach ($newCollection as $key => $item) {
            $rp = new ReflectionProperty($item::class, $mappedBy);
            $subArgs = new TranslationArgs($item, $args->getSourceLocale(), $args->getTargetLocale())
                ->setTranslatedParent($newOwner)
                ->setProperty($rp);

            $itemTrans = $this->translator->processTranslation($subArgs);

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
            return $newCollection;
        }

        $meta = $this->em->getClassMetadata($newOwner::class);
        $mappings = $meta->getAssociationMappings();
        $assoc = $mappings[$prop->name] ?? null;
        if ($assoc === null) {
            throw new RuntimeException(sprintf(
                'Association mapping not found for property "%s" on "%s".',
                $prop->name,
                $newOwner::class
            ));
        }

        $mappedBy = $assoc['mappedBy'] ?? null;
        if ($mappedBy === null) {
            throw new RuntimeException(sprintf(
                'Association "%s::%s" is not a bidirectional ManyToMany (missing mappedBy).',
                $newOwner::class,
                $prop->name
            ));
        }

        foreach ($newCollection as $key => $item) {
            $rp = new ReflectionProperty($item::class, $mappedBy);

            $rp->setValue($item, new ArrayCollection([]));

            $itemTrans = $this->translator->translate($item, $args->getTargetLocale());

            $rp->setValue($itemTrans, new ArrayCollection([$newOwner]));
            $newCollection[$key] = $itemTrans;
        }

        return $newCollection;
    }
}