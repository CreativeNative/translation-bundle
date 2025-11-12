<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Proxy;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;

/**
 * Handles basic Doctrine objects. Usually the entry point for translating an entity.
 *
 * Notes:
 * - PropertyAccessorInterface can be optionally injected for testability.
 */
final readonly class DoctrineObjectHandler implements TranslationHandlerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EntityTranslatorInterface $translator,
        private PropertyAccessorInterface|null $accessor = null,
    ) {
    }

    /**
     * True when $args->getDataToBeTranslated() is a Doctrine-managed class. *.
     */
    public function supports(TranslationArgs $args): bool
    {
        $data = $args->getDataToBeTranslated();

        if (\is_object($data)) {
            // If proxy, use parent class name for metadata lookup
            $data = $data instanceof Proxy ? get_parent_class($data) : $data::class;
        }

        try {
            return !$this->entityManager->getMetadataFactory()->isTransient($data);
        } catch (\Throwable $e) {
            // Rewrap low-level exceptions for clearer runtime reporting
            throw new \RuntimeException(sprintf('DoctrineObjectHandler::supports: failed to determine metadata for "%s": %s', \is_object($data) ? $data::class : (string) $data, $e->getMessage()), 0, $e);
        }
    }

    public function handleSharedAmongstTranslations(TranslationArgs $args): mixed
    {
        return $args->getDataToBeTranslated();
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): null
    {
        return null;
    }

    /**
     * Clone the object and translate its properties.
     *
     * @throws \ReflectionException
     */
    public function translate(TranslationArgs $args): mixed
    {
        $data = $args->getDataToBeTranslated();
        if (!\is_object($data)) {
            throw new \RuntimeException('DoctrineObjectHandler::translate expects an object.');
        }

        $clone = clone $data;
        $args->setDataToBeTranslated($clone);

        $this->translateProperties($args);

        return $args->getDataToBeTranslated();
    }

    /**
     * Iterate over object properties and dispatch translation for each one via the translator.
     *
     * @throws \ReflectionException
     */
    public function translateProperties(TranslationArgs $args): void
    {
        $translation = $args->getDataToBeTranslated();
        if (!\is_object($translation)) {
            throw new \RuntimeException('translateProperties expects object in TranslationArgs.');
        }

        // allow injection for tests; otherwise create default accessor
        $accessor = $this->accessor ?? PropertyAccess::createPropertyAccessor();

        $reflect    = new \ReflectionClass($translation::class);
        $properties = $reflect->getProperties();

        foreach ($properties as $property) {
            // read the current value (let exceptions bubble as runtime)
            try {
                $propValue = $accessor->getValue($translation, $property->name);
            } catch (NoSuchPropertyException) {
                // If property is not accessible by accessor, fallback to reflection read
                $rp        = new \ReflectionProperty($translation::class, $property->name);
                $propValue = $rp->getValue($translation);
            }

            if (null === $propValue) {
                continue;
            }

            if ($propValue instanceof Collection && $propValue->isEmpty()) {
                continue;
            }

            $subArgs = new TranslationArgs(
                $propValue,
                $args->getSourceLocale(),
                $args->getTargetLocale(),
            );
            $subArgs->setTranslatedParent($translation)->setProperty($property);

            // Delegate translation of the property value to the global translator
            $propertyTranslation = $this->translator->processTranslation($subArgs);

            // try to set via accessor; if it throws NoSuchPropertyException, fallback to reflection
            try {
                $accessor->setValue($translation, $property->name, $propertyTranslation);
            } catch (NoSuchPropertyException) {
                $rp = new \ReflectionProperty($translation::class, $property->name);
                $rp->setValue($translation, $propertyTranslation);
            }
        }
    }
}
