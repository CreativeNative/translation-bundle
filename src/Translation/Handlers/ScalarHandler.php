<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Tmi\TranslationBundle\Translation\Context\TranslationContext;
use Tmi\TranslationBundle\Utils\AttributeHelper;
use Tmi\TranslationBundle\Utils\ReflectionHelper;

/**
 * Handles scalars and value objects: every property value that is neither a
 * Doctrine-mapped object, nor an embeddable, nor a Collection.
 *
 * Value objects -- `\DateTimeInterface`, enums, `Uid`, `Money`, any transient class --
 * belong here and not to a "no handler matched" fall-through: the attribute cascade
 * in EntityTranslator::runHandlers() (`#[SharedAmongstTranslations]`,
 * `#[EmptyOnTranslate]`, `copy_source: false`) only runs INSIDE a supporting handler,
 * so a value without one was silently copied whatever its attributes said.
 * `#[EmptyOnTranslate]` on a `DateTimeImmutable` did nothing before 5.2.
 */
final readonly class ScalarHandler implements TranslationHandlerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AttributeHelper $attributeHelper,
    ) {
    }

    #[\Override]
    public function supports(TranslationContext $context): bool
    {
        $data = $context->getSubject();

        if (!\is_object($data)) {
            return true;
        }

        // A Collection is an association's value, the collection handlers' business.
        if ($data instanceof Collection) {
            return false;
        }

        // An embeddable is transient to Doctrine's driver too; EmbeddedHandler (which
        // runs after this handler) owns it, so it is told apart by the property's mapping.
        $property = $context->getProperty();

        if (null !== $property && $this->attributeHelper->isEmbedded($property)) {
            return false;
        }

        return $this->entityManager->getMetadataFactory()->isTransient(ReflectionHelper::realClass($data));
    }

    /**
     * Shared keeps the source value; empty clears it to null -- a scalar has no
     * meaningful non-null "empty" default of its own, and EntityTranslator already
     * falls back to TypeDefaultResolver for a non-nullable property before ever
     * reaching here, so this handler only sees isEmpty() on a nullable one.
     *
     * A mutable `\DateTime` is cloned so an edit through one locale variant cannot
     * bleed into its siblings; every other value is handed over as is -- immutable
     * by contract (`DateTimeImmutable`, enums, uids) or the application's own value
     * object, which it shares knowingly.
     */
    #[\Override]
    public function translate(TranslationContext $context): mixed
    {
        if ($context->isEmpty()) {
            return null;
        }

        $subject = $context->getSubject();

        return $subject instanceof \DateTime ? clone $subject : $subject;
    }
}
