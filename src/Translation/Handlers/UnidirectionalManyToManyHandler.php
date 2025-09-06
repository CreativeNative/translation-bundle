<?php
declare(strict_types=1);

namespace TMI\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToMany;
use ErrorException;
use RuntimeException;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslatorInterface;
use TMI\TranslationBundle\Utils\AttributeHelper;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Handles ManyToMany unidirectional associations during translation.
 */
final readonly class UnidirectionalManyToManyHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper           $attributeHelper,
        private EntityTranslatorInterface $translator,
        private EntityManagerInterface    $em,
    ) {}

    public function supports(TranslationArgs $args): bool
    {
        $data = $args->getDataToBeTranslated();
        $property = $args->getProperty();

        if (!$data instanceof Collection || $property === null) {
            return false;
        }

        if (!$this->attributeHelper->isManyToMany($property)) {
            return false;
        }

        $attributes = $property->getAttributes(ManyToMany::class);
        if ($attributes === []) {
            return false;
        }

        $arguments = $attributes[0]->getArguments();

        // Unidirectional = neither mappedBy nor inversedBy is set
        return !isset($arguments['mappedBy']) && !isset($arguments['inversedBy']);
    }

    /**
     * SharedAmongstTranslations is not supported for ManyToMany unidirectional collections.
     *
     * @throws ErrorException
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): Collection
    {
        $property = $args->getProperty();
        if ($property !== null && $this->attributeHelper->isManyToMany($property)) {
            throw new ErrorException(sprintf(
                'SharedAmongstTranslations is not supported for ManyToMany associations (%s::%s).',
                $property->class,
                $property->name
            ));
        }

        // fallback: perform a normal translation (defensive)
        return $this->translate($args);
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): Collection
    {
        return new ArrayCollection();
    }

    /**
     * Translate the collection items and replace the collection entries with translated items.
     *
     */
    public function translate(TranslationArgs $args): Collection
    {
        $newOwner = $args->getTranslatedParent();
        $property = $args->getProperty();

        if ($newOwner === null) {
            throw new RuntimeException('No translated parent provided.');
        }

        if ($property === null) {
            throw new RuntimeException(sprintf(
                'No property given for parent of class "%s".',
                $newOwner::class
            ));
        }

        $meta = $this->em->getClassMetadata($newOwner::class);
        $associations = $meta->getAssociationMappings();
        $association = $associations[$property->name] ?? null;

        if ($association === null) {
            throw new RuntimeException(sprintf(
                'Property "%s" is not a valid association in class "%s".',
                $property->name,
                $newOwner::class
            ));
        }

        if (!($association['isOwningSide'] ?? false)) {
            throw new RuntimeException(sprintf(
                'Property "%s" on "%s" is not the owning side of the relation.',
                $property->name,
                $newOwner::class
            ));
        }

        $fieldName = (string) $association['fieldName'];
        $accessor = new PropertyAccessor();

        if (!property_exists($newOwner, $fieldName)) {
            throw new RuntimeException(sprintf(
                'Field "%s" not found in class "%s".',
                $fieldName,
                $newOwner::class
            ));
        }

        /** @var Collection<int, object>|null $collection */
        $collection = $accessor->getValue($newOwner, $fieldName);

        if (!$collection instanceof Collection) {
            $collection = new ArrayCollection();
            $accessor->setValue($newOwner, $fieldName, $collection);
        }

        // ---------- CRITICAL FIX ----------
        // copy items to translate BEFORE clearing the collection.
        $itemsToTranslate = [];
        foreach ($args->getDataToBeTranslated() as $item) {
            $itemsToTranslate[] = $item;
        }

        // clear target collection (safe now because we have a copy)
        $collection->clear();

        foreach ($itemsToTranslate as $item) {

            $translated = $this->translator->translate($item, $args->getTargetLocale());

            if ($translated === null) {
                throw new RuntimeException(sprintf(
                    'Translator returned null for item of type "%s".',
                    is_object($item) ? $item::class : gettype($item)
                ));
            }

            if (!$collection->contains($translated)) {
                $collection->add($translated);
            }
        }

        return $collection;
    }
}
