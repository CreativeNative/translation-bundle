<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;

interface EntityTranslatorInterface
{
    public function translate(TranslatableInterface $entity, string $locale): TranslatableInterface;

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

    /** Called after an entity is loaded. */
    public function afterLoad(TranslatableInterface $entity): void;

    /** Called before an entity is persisted. */
    public function beforePersist(TranslatableInterface $entity): void;

    /** Called before an entity is updated. */
    public function beforeUpdate(TranslatableInterface $entity): void;

    /** Called before an entity is removed. */
    public function beforeRemove(TranslatableInterface $entity): void;
}
