<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation;

use Doctrine\ORM\EntityManagerInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;

interface EntityTranslatorInterface
{
    public function translate(TranslatableInterface $entity, string $locale): TranslatableInterface;

    /** Expose processTranslation so handlers may ask translator to translate sub-objects */
    public function processTranslation(TranslationArgs $args): mixed;

    /** Called after an entity is loaded. */
    public function afterLoad(TranslatableInterface $entity): void;

    /** Called before an entity is persisted. */
    public function beforePersist(TranslatableInterface $entity, EntityManagerInterface $em): void;

    /** Called before an entity is updated. */
    public function beforeUpdate(TranslatableInterface $entity, EntityManagerInterface $em): void;

    /** Called before an entity is removed. */
    public function beforeRemove(TranslatableInterface $entity, EntityManagerInterface $em): void;
}
