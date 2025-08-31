<?php

namespace TMI\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\MappingException;
use Doctrine\Persistence\Proxy;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\EntityTranslator;
use function is_object;

/**
 * Handles basic Doctrine Objects.
 * Usually the entry point of a translation.
 */
final class DoctrineObjectHandler implements TranslationHandlerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EntityTranslator $translator
    ) {
    }

    /**
     * @throws MappingException
     */
    public function supports(TranslationArgs $args): bool
    {
        $data = $args->getDataToBeTranslated();

        if (is_object($data)) {
            $data = ($data instanceof Proxy) ? get_parent_class($data) : $data::class;
        }

        return !$this->em->getMetadataFactory()->isTransient($data);
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
     * @throws ReflectionException
     */
    public function translate(TranslationArgs $args): mixed
    {
        $clone = clone $args->getDataToBeTranslated();

        $args->setDataToBeTranslated($clone);
        $this->translateProperties($args);

        return $args->getDataToBeTranslated();
    }

    /**
     * Loops through all object properties to translate them.
     * @throws ReflectionException
     */
    public function translateProperties(TranslationArgs $args): void
    {
        $translation = $args->getDataToBeTranslated();
        $accessor = PropertyAccess::createPropertyAccessor();
        $reflect = new ReflectionClass($translation::class);
        $properties = $reflect->getProperties();

        foreach ($properties as $property) {
            $propValue = $accessor->getValue($translation, $property->name);

            if (empty($propValue) || ($propValue instanceof Collection && $propValue->isEmpty())) {
                continue;
            }

            $subTranslationArgs = new TranslationArgs(
                $propValue,
                $args->getSourceLocale(),
                $args->getTargetLocale()
            )->setTranslatedParent($translation)
                ->setProperty($property);

            $propertyTranslation = $this->translator->processTranslation($subTranslationArgs);

            try {
                $accessor->setValue($translation, $property->name, $propertyTranslation);
            } catch (NoSuchPropertyException) {
                $reflection = new ReflectionProperty($translation::class, $property->name);
                $reflection->setValue($translation, $propertyTranslation);
            }
        }
    }
}
