<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Context;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * "Translate this entity" -- the call shape used by EntityTranslator::translate() for
 * a top-level call, and by every handler that walks into a ManyToOne/OneToOne/OneToMany
 * association and hands the related TranslatableInterface back to the translator
 * (TranslatableEntityHandler, BidirectionalManyToOneHandler, BidirectionalOneToOneHandler,
 * BidirectionalOneToManyHandler).
 *
 * {@see TranslationContext::getProperty()}/{@see TranslationContext::getTranslatedParent()}
 * are null for the top-level call and set for the association case, so the handler that
 * receives the translated clone back can repair the association's back-reference (see
 * the handlers listed above).
 */
final class EntityTranslationContext extends TranslationContext
{
    public function __construct(
        private TranslatableInterface $entity,
        string|null $sourceLocale = null,
        string|null $targetLocale = null,
    ) {
        parent::__construct($sourceLocale, $targetLocale);
    }

    public function getEntity(): TranslatableInterface
    {
        return $this->entity;
    }

    public function getSubject(): mixed
    {
        return $this->entity;
    }

    public function setSubject(mixed $subject): static
    {
        \assert($subject instanceof TranslatableInterface);
        $this->entity = $subject;

        return $this;
    }
}
