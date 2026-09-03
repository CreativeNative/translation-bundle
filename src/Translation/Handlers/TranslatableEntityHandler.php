<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Context\EntityTranslationContext;
use Tmi\TranslationBundle\Translation\Context\TranslationContext;
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

    public function supports(TranslationContext $context): bool
    {
        return $context instanceof EntityTranslationContext;
    }

    /**
     * @throws \ReflectionException
     */
    public function translate(TranslationContext $context): TranslatableInterface|null
    {
        \assert($context instanceof EntityTranslationContext);

        // Reached only for the direct (unidirectional) association form: the
        // bidirectional handlers never delegate here while shared/empty, since their
        // own translate() branches on those facts before ever calling this one.
        if ($context->isEmpty()) {
            return null;
        }

        $data = $context->getEntity();

        $targetLocale = $context->getTargetLocale();
        \assert(\is_string($targetLocale));

        // Search across every locale variant of the Tuuid, not just the ones visible
        // under the current locale filter -- an in-filter lookup here would never see
        // an existing variant in $targetLocale and mint a duplicate row on every call.
        $existingTranslation = $this->finder->findLocaleVariant($data::class, $data->getTuuid(), $targetLocale);

        if (null !== $existingTranslation) {
            return $existingTranslation;
        }

        $clone = clone $data;

        $subContext = new EntityTranslationContext($clone, $clone->getLocale(), $targetLocale);
        $subContext->setCopySource($context->getCopySource());
        $this->doctrineObjectHandler->translateProperties($subContext);

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
