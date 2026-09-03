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
 * Rejects a #[SharedAmongstTranslations] association whose target is itself a
 * translatable entity: translate() throws instead of silently cloning the target.
 * This is the catch-all handler for a unidirectional ManyToOne/OneToOne (no
 * inversedBy/mappedBy) -- none of the five other association handlers' supports()
 * match that shape, so when such an association also carries
 * #[SharedAmongstTranslations], this is the first and only handler to see the
 * context, and it must reject it itself rather than let it fall through untreated.
 * Sharing an association to a NON-translatable target is unaffected and keeps
 * working exactly as today, through DoctrineObjectHandler's own isShared() branch,
 * which this handler never touches.
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
     * @throws \RuntimeException
     */
    public function translate(TranslationContext $context): TranslatableInterface|null
    {
        \assert($context instanceof EntityTranslationContext);

        // Reached directly from EntityTranslator::runHandlers() for the unidirectional
        // ManyToOne/OneToOne case (no inversedBy/mappedBy): none of the five other
        // handlers' supports() match a plain association property, so this catch-all is
        // the one that sees isShared() first and must reject it here itself instead of
        // silently translating the target. The bidirectional handlers never delegate
        // here while shared -- BidirectionalManyToOneHandler/BidirectionalOneToOneHandler
        // both branch on their own context->isShared() and throw before ever calling this
        // handler's translate() -- so this check only ever fires for the direct,
        // unidirectional path.
        if ($context->isShared()) {
            $property = $context->getProperty();
            $message  = '#[SharedAmongstTranslations] is not supported on an association to a translatable '.
                'entity. Property "%prop%" of class "%class%" points at a translatable target -- share the '.
                'related entity\'s own columns instead of the association.';

            throw new \RuntimeException(strtr($message, ['%class%' => $context->getEntity()::class, '%prop%' => null !== $property ? $property->name : 'unknown']));
        }

        // Reached only for the direct (unidirectional) association form: the
        // bidirectional handlers never delegate here while empty, since their
        // own translate() branches on that fact before ever calling this one.
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
