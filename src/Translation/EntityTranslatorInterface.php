<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;

interface EntityTranslatorInterface
{
    public function translate(TranslatableInterface $entity, string $locale): TranslatableInterface;

    /**
     * Translates the entity and persists the result via the EntityManager.
     */
    public function translateAndPersist(TranslatableInterface $entity, string $locale): TranslatableInterface;

    /**
     * Returns an existing translation or creates, persists and returns a new one.
     */
    public function getOrTranslate(TranslatableInterface $entity, string $locale): TranslatableInterface;

    /**
     * Batch-loads each entity's $locale variant into the cache ahead of time,
     * one query per class rather than one per entity.
     *
     * translate() already warms its own cache entry before running the
     * handler chain, so a loop calling translate() one entity at a time
     * costs one lookup query per entity regardless. Calling preload() with
     * the whole batch first turns that into one lookup query per class --
     * translate() then finds every entry already cached and skips its own
     * lookup. Entities without a $locale variant are simply not found and
     * are translated (created) normally when translate() reaches them.
     *
     * @param iterable<mixed> $entities
     */
    public function preload(iterable $entities, string $locale): void;

    /**
     * Process translation for an entity, embedded object, or property value.
     *
     * Exposed so handlers may recursively translate sub-objects through the
     * orchestrator's handler chain.
     *
     * @param TranslationArgs $args contains the data to translate, source/target locales, and optional parent context
     *
     * @return mixed translated entity (TranslatableInterface), embedded object, or scalar property value
     */
    public function processTranslation(TranslationArgs $args): mixed;
}
