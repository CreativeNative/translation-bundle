<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Utils\AttributeHelper;
use Tmi\TranslationBundle\Utils\ReflectionHelper;

final readonly class TranslatableEntityHandler implements TranslationHandlerInterface
{
    public function __construct(
        private LocaleVariantFinder $finder,
        private DoctrineObjectHandler $doctrineObjectHandler,
        private AttributeHelper $attributeHelper,
    ) {
    }

    public function supports(TranslationArgs $args): bool
    {
        return $args->getDataToBeTranslated() instanceof TranslatableInterface;
    }

    /**
     * @throws \ReflectionException
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): TranslatableInterface
    {
        return $this->translate($args);
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): null
    {
        return null;
    }

    /**
     * @throws \ReflectionException
     */
    public function translate(TranslationArgs $args): TranslatableInterface
    {
        $data = $args->getDataToBeTranslated();
        \assert($data instanceof TranslatableInterface);

        $targetLocale = $args->getTargetLocale();
        \assert(\is_string($targetLocale));

        // Search across every locale variant of the Tuuid, not just the ones visible
        // under the current locale filter -- an in-filter lookup here would never see
        // an existing variant in $targetLocale and mint a duplicate row on every call.
        $existingTranslation = $this->finder->findLocaleVariant($data::class, $data->getTuuid(), $targetLocale);

        if (null !== $existingTranslation) {
            return $existingTranslation;
        }

        $clone = clone $data;

        $subArgs = new TranslationArgs($clone, $clone->getLocale(), $targetLocale);
        $subArgs->setCopySource($args->getCopySource());
        $this->doctrineObjectHandler->translateProperties($subArgs);

        $this->resetGeneratedIds($clone);
        $clone->setLocale($targetLocale);

        return $clone;
    }

    private function resetGeneratedIds(TranslatableInterface $clone): void
    {
        // Hierarchy-aware walk: a generated id declared private on a mapped
        // superclass must not keep the source's value on the clone.
        foreach (ReflectionHelper::getHierarchyProperties(new \ReflectionClass($clone)) as $property) {
            if ($this->attributeHelper->isId($property) && $this->attributeHelper->isGeneratedValue($property)) {
                $property->setValue($clone, null);
            }
        }
    }
}
