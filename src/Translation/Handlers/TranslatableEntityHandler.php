<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Context\EntityTranslationContext;
use Tmi\TranslationBundle\Translation\Context\TranslationContext;
use Tmi\TranslationBundle\Utils\AttributeHelper;
use Tmi\TranslationBundle\Utils\ReflectionHelper;

/**
 * Clones a translatable entity into its target locale, running the clone through
 * the property pipeline and resetting its generated id(s).
 *
 * Does not check for an existing $targetLocale variant itself -- that existence
 * question is resolved exactly once, before this handler ever runs, by
 * EntityTranslator::processTranslation()'s own preload()-then-cache-check for the
 * same subject (see that method's docblock). This handler is reached only two
 * ways: (a) from EntityTranslator::runHandlers(), always after that preload() ran
 * for the same entity, or (b) from BidirectionalManyToOneHandler /
 * BidirectionalOneToOneHandler, which are themselves reached the same way for the
 * same subject and simply pass their own context through. Calling translate()
 * any other way -- a custom handler reaching for this class directly instead of
 * delegating through EntityTranslatorInterface::translate()/processTranslation()
 * -- skips that existence check entirely and always clones, minting a duplicate
 * row for any Tuuid that already has a variant in the target locale.
 */
final readonly class TranslatableEntityHandler implements TranslationHandlerInterface
{
    public function __construct(
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
