<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Proxy;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Context\EntityTranslationContext;
use Tmi\TranslationBundle\Translation\Context\PropertyTranslationContext;
use Tmi\TranslationBundle\Translation\Context\TranslationContext;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;
use Tmi\TranslationBundle\Utils\ReflectionHelper;

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
     * True when $context->getSubject() is a Doctrine-managed class. *.
     */
    public function supports(TranslationContext $context): bool
    {
        $data = $context->getSubject();

        if (!\is_object($data)) {
            return false;
        }

        // If proxy, use parent class name for metadata lookup
        $parentClass = $data instanceof Proxy ? get_parent_class($data) : false;
        $className   = \is_string($parentClass) ? $parentClass : $data::class;

        try {
            return !$this->entityManager->getMetadataFactory()->isTransient($className);
        } catch (\Throwable $e) {
            // Rewrap low-level exceptions for clearer runtime reporting
            throw new \RuntimeException(sprintf('DoctrineObjectHandler::supports: failed to determine metadata for "%s": %s', $className, $e->getMessage()), 0, $e);
        }
    }

    /**
     * Clone the object and translate its properties.
     *
     * Shared/empty resolution for a plain Doctrine object (neither an entity's own
     * translatable properties -- those go through TranslatableEntityHandler -- nor an
     * embeddable) is identity: the source instance itself for #[SharedAmongstTranslations],
     * null for #[EmptyOnTranslate]. Both facts are pre-resolved by EntityTranslator only
     * for property-shaped calls, so an entity-shaped context (property unset) never sets
     * either and always falls through to the clone-and-translate-properties path below.
     *
     * @throws \ReflectionException
     */
    public function translate(TranslationContext $context): mixed
    {
        if ($context->isShared()) {
            return $context->getSubject();
        }

        if ($context->isEmpty()) {
            return null;
        }

        $data = $context->getSubject();
        if (!\is_object($data)) {
            throw new \RuntimeException('DoctrineObjectHandler::translate expects an object.');
        }

        $clone = clone $data;
        $context->setSubject($clone);

        $this->translateProperties($context);

        return $context->getSubject();
    }

    /**
     * Iterate over object properties and dispatch translation for each one via the translator.
     *
     * @throws \ReflectionException
     */
    public function translateProperties(TranslationContext $context): void
    {
        $translation = $context->getSubject();
        if (!\is_object($translation)) {
            throw new \RuntimeException('translateProperties expects an object as the context subject.');
        }

        // allow injection for tests; otherwise create default accessor
        $accessor = $this->accessor ?? PropertyAccess::createPropertyAccessor();

        $reflect    = new \ReflectionClass($translation::class);
        $properties = ReflectionHelper::getHierarchyProperties($reflect);

        foreach ($properties as $property) {
            // read the current value (let exceptions bubble as runtime)
            try {
                $propValue = $accessor->getValue($translation, $property->name);
            } catch (NoSuchPropertyException) {
                // If property is not accessible by accessor, fallback to reflection read.
                // $property itself must do the reading — constructing a ReflectionProperty
                // from the child class name fails for private parent-class properties.
                $propValue = $property->getValue($translation);
            }

            if (null === $propValue) {
                continue;
            }

            if ($propValue instanceof Collection && $propValue->isEmpty()) {
                // clone $data is shallow: without this, the clone's Collection property
                // still points at the exact same object as the source's. Left alone, an
                // add() on the clone's (supposedly empty) collection would silently
                // mutate the source's too. A readonly property is already initialised
                // and PHP rejects every write to it -- there the shared reference is
                // unavoidable, so it is left alone rather than thrown on.
                if (!$property->isReadOnly()) {
                    try {
                        $accessor->setValue($translation, $property->name, new ArrayCollection());
                    } catch (NoSuchPropertyException) {
                        $property->setValue($translation, new ArrayCollection());
                    }
                }

                continue;
            }

            // A translatable association walks back into the entity pipeline (existing-
            // variant lookup, id reset, back-reference repair); every other property
            // shape (scalar, embeddable, Collection) is a plain property-value resolution.
            $subContext = $propValue instanceof TranslatableInterface
                ? new EntityTranslationContext($propValue, $context->getSourceLocale(), $context->getTargetLocale())
                : new PropertyTranslationContext($propValue, $context->getSourceLocale(), $context->getTargetLocale());
            $subContext->setTranslatedParent($translation)
                ->setProperty($property)
                ->setCopySource($context->getCopySource());

            // Delegate translation of the property value to the global translator
            $propertyTranslation = $this->translator->processTranslation($subContext);

            // A readonly property is already initialised on the clone and PHP rejects every
            // write to it, even one that would store the identical value. Skipping the write
            // is exactly right whenever the resolved value did not change -- which is the
            // case for readonly properties that are shared or simply copied.
            if ($property->isReadOnly()) {
                if (self::isUnchanged($propValue, $propertyTranslation)) {
                    continue;
                }

                throw new \LogicException(sprintf('Property %s::$%s is readonly and cannot be reassigned while translating. Mark it #[SharedAmongstTranslations] so every locale keeps the same value, or drop the readonly modifier.', $property->class, $property->name));
            }

            // try to set via accessor; if it throws NoSuchPropertyException, fallback to reflection
            try {
                $accessor->setValue($translation, $property->name, $propertyTranslation);
            } catch (NoSuchPropertyException) {
                $property->setValue($translation, $propertyTranslation);
            }
        }
    }

    /**
     * Whether writing $resolved would leave the property exactly as it already is.
     *
     * Objects compare by state, not identity: a handler that hands back an equal clone --
     * an untouched embeddable, for instance -- stores nothing new.
     */
    private static function isUnchanged(mixed $current, mixed $resolved): bool
    {
        if ($current === $resolved) {
            return true;
        }

        return is_object($current)
            && is_object($resolved)
            && $current::class     === $resolved::class
            && serialize($current) === serialize($resolved);
    }
}
