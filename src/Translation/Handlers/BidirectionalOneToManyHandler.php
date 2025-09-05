<?php

namespace TMI\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\OneToMany;
use ErrorException;
use ReflectionException;
use ReflectionProperty;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslatorInterface;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * Handles translation of OneToMany relations.
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
        if ($args->getProperty() && $this->attributeHelper->isOneToMany($args->getProperty())) {
            $arguments = $args->getProperty()->getAttributes(OneToMany::class)[0]->getArguments();

            return array_key_exists('mappedBy', $arguments) && null !== $arguments['mappedBy'];
        }

        return false;
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

    public function handleEmptyOnTranslate(TranslationArgs $args): null
    {
        return new ArrayCollection();
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

            $subTranslationArgs = new TranslationArgs($item, $args->getSourceLocale(), $args->getTargetLocale())
                ->setTranslatedParent($newOwner)
                ->setProperty($reflection);

            $itemTrans = $this->translator->processTranslation($subTranslationArgs);

            $reflection->setValue($itemTrans, $newOwner);
            $newCollection[$key] = $itemTrans;
        }

        return $newCollection;
    }
}
