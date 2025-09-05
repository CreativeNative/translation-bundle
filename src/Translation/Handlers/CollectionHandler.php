<?php

namespace TMI\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToMany;
use ReflectionException;
use ReflectionProperty;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslatorInterface;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * Collection handler, used for ManyToMany bidirectional association.
 */
final readonly class CollectionHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper           $attributeHelper,
        private EntityManagerInterface    $em,
        private EntityTranslatorInterface $translator
    )
    {
    }

    public function supports(TranslationArgs $args): bool
    {
        if (!$args->getDataToBeTranslated() instanceof Collection) {
            return false;
        }

        if ($args->getProperty() && $this->attributeHelper->isManyToMany($args->getProperty())) {
            $arguments = $args->getProperty()->getAttributes(ManyToMany::class)[0]->getArguments();
            return array_key_exists('mappedBy', $arguments) && null !== $arguments['mappedBy'];
        }

        return false;
    }

    /**
     * @throws ReflectionException
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): mixed
    {
        $collection = $args->getDataToBeTranslated();
        assert($collection instanceof Collection);
        $newCollection = clone $collection;
        $newOwner = $args->getTranslatedParent();
        if ($newOwner === null || $args->getProperty() === null) {
            return new ArrayCollection();
        }

        $associations = $this->em->getClassMetadata($newOwner::class)->getAssociationMappings();
        $association = $associations[$args->getProperty()->name];
        $mappedBy = $association['mappedBy'];

        foreach ($newCollection as $key => $item) {
            $reflection = new ReflectionProperty($item::class, $mappedBy);

            $subTranslationArgs = new TranslationArgs(
                $item,
                $args->getSourceLocale(),
                $args->getTargetLocale()
            )->setTranslatedParent($newOwner)
                ->setProperty($reflection);

            $itemTrans = $this->translator->processTranslation($subTranslationArgs);
            $reflection->setValue($itemTrans, new ArrayCollection([$newOwner]));
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
    public function translate(TranslationArgs $args): mixed
    {
        $collection = $args->getDataToBeTranslated();
        assert($collection instanceof Collection);
        $newCollection = clone $collection;
        $newOwner = $args->getTranslatedParent();
        if ($newOwner === null || $args->getProperty() === null) {
            return new ArrayCollection();
        }

        $associations = $this->em->getClassMetadata($newOwner::class)->getAssociationMappings();
        $association = $associations[$args->getProperty()->name];
        $mappedBy = $association['mappedBy'];

        foreach ($newCollection as $key => $item) {
            $reflection = new ReflectionProperty($item::class, $mappedBy);

            $reflection->setValue($item, new ArrayCollection([]));
            $itemTrans = $this->translator->translate($item, $args->getTargetLocale());
            $reflection->setValue($itemTrans, new ArrayCollection([$newOwner]));
            $newCollection[$key] = $itemTrans;
        }

        return $newCollection;
    }
}
