<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\TranslatableRemover;

/**
 * Opt-in cascade: when `tmi_translation.cascade_remove_locale_variants` is
 * enabled, a plain `$em->remove($translatable)` also schedules every sibling
 * locale variant sharing its Tuuid for removal.
 *
 * Always registered — `$enabled` decides at runtime whether `preRemove()`
 * does anything, so toggling the config option needs no service redefinition.
 *
 * Delegates to {@see TranslatableRemover::cascadeFromPreRemove()}, which is
 * `@internal` precisely because this listener is its intended caller: it
 * only ever runs from inside a `preRemove` event, the guarantee that method
 * relies on to schedule siblings safely.
 */
#[AsDoctrineListener(event: Events::preRemove)]
final readonly class LocaleVariantRemovalListener
{
    public function __construct(
        private TranslatableRemover $remover,
        private bool $enabled,
    ) {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        if (!$this->enabled) {
            return;
        }

        $entity = $args->getObject();

        if (!$entity instanceof TranslatableInterface) {
            return;
        }

        $this->remover->cascadeFromPreRemove($entity);
    }
}
