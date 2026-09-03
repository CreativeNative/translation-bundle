<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Context;

/**
 * Shared shape of the two typed translation contexts.
 *
 * A translation call is always one of exactly two shapes: translate a
 * TranslatableInterface entity ({@see EntityTranslationContext}), or translate the
 * value found on a property of some already-translated parent
 * ({@see PropertyTranslationContext}). Locale/copySource/property bookkeeping is
 * identical for both shapes and lives here; the subject being translated differs in
 * type between them ({@see self::getSubject()}), which is why it is not declared here.
 *
 * Mirrors the old TranslationArgs' mutability and nullability -- fields are set
 * fluently after construction (source/target locale can be null, resolved lazily by
 * EntityTranslator; property/translatedParent are absent for a top-level entity call)
 * rather than treated as immutable value objects, so existing call sites building a
 * context step by step keep working unchanged.
 */
abstract class TranslationContext
{
    private string|null $sourceLocale;

    private string|null $targetLocale;

    private bool|null $copySource = null;

    private \ReflectionProperty|null $property = null;

    private object|null $translatedParent = null;

    private bool $shared = false;

    private bool $empty = false;

    protected function __construct(string|null $sourceLocale, string|null $targetLocale)
    {
        $this->sourceLocale = $sourceLocale;
        $this->targetLocale = $targetLocale;
    }

    /**
     * Returns the thing actually being translated: a TranslatableInterface for
     * {@see EntityTranslationContext}, or the mixed property value for
     * {@see PropertyTranslationContext}.
     */
    abstract public function getSubject(): mixed;

    /**
     * Replaces the subject in place, keeping every other field.
     *
     * Used by DoctrineObjectHandler::translate() to swap the original object for its
     * clone before translating properties on it.
     */
    abstract public function setSubject(mixed $subject): static;

    public function getSourceLocale(): string|null
    {
        return $this->sourceLocale;
    }

    public function setSourceLocale(string|null $sourceLocale): static
    {
        $this->sourceLocale = $sourceLocale;

        return $this;
    }

    public function getTargetLocale(): string|null
    {
        return $this->targetLocale;
    }

    public function setTargetLocale(string|null $targetLocale): static
    {
        $this->targetLocale = $targetLocale;

        return $this;
    }

    /**
     * Null means "not yet resolved for this entity" -- EntityTranslator resolves it
     * once per entity, from that entity's own #[Translatable] attribute or the global
     * default, before dispatching to the handler chain.
     */
    public function getCopySource(): bool|null
    {
        return $this->copySource;
    }

    public function setCopySource(bool|null $copySource): static
    {
        $this->copySource = $copySource;

        return $this;
    }

    /**
     * The property this translation was reached through, if any. Null for a
     * top-level EntityTranslationContext (EntityTranslator::translate() called
     * directly); set for the association case and for every PropertyTranslationContext.
     */
    public function getProperty(): \ReflectionProperty|null
    {
        return $this->property;
    }

    public function setProperty(\ReflectionProperty|null $property): static
    {
        $this->property = $property;

        return $this;
    }

    /**
     * The already-translated owner this subject belongs to, if any. Null for a
     * top-level EntityTranslationContext; set for the association case and for every
     * PropertyTranslationContext.
     */
    public function getTranslatedParent(): object|null
    {
        return $this->translatedParent;
    }

    public function setTranslatedParent(object|null $translatedParent): static
    {
        $this->translatedParent = $translatedParent;

        return $this;
    }

    /**
     * Whether EntityTranslator determined -- from the property's attributes, before
     * dispatching to the handler chain -- that this call is a
     * #[SharedAmongstTranslations] resolution. Handlers branch on this instead of
     * exposing a second interface method.
     */
    public function isShared(): bool
    {
        return $this->shared;
    }

    public function setShared(bool $shared): static
    {
        $this->shared = $shared;

        return $this;
    }

    /**
     * Whether EntityTranslator determined -- from the property's attributes, before
     * dispatching to the handler chain -- that this call is an #[EmptyOnTranslate]
     * resolution. Handlers branch on this instead of exposing a second interface method.
     */
    public function isEmpty(): bool
    {
        return $this->empty;
    }

    public function setEmpty(bool $empty): static
    {
        $this->empty = $empty;

        return $this;
    }
}
