<?php

declare(strict_types=1);

namespace TMI\TranslationBundle\Translation;

use Doctrine\ORM\EntityManagerInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;

interface EntityTranslatorInterface
{
    public function translate(TranslatableInterface $entity, string $locale): TranslatableInterface;

    /**
     * Called after an entity is loaded.
     */
    public function afterLoad(TranslatableInterface $entity): void;

    /**
     * Called before an entity is persisted.
     */
    public function beforePersist(TranslatableInterface $entity, EntityManagerInterface $em): void;

    /**
     * Called before an entity is updated.
     */
    public function beforeUpdate(TranslatableInterface $entity, EntityManagerInterface $em): void;

    /**
     * Called before an entity is removed.
     */
    public function beforeRemove(TranslatableInterface $entity, EntityManagerInterface $em): void;
}
