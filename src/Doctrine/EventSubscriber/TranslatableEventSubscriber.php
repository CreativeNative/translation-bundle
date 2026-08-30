<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Exception\OrphanTranslationException;
use Tmi\TranslationBundle\Translation\EntityTranslatorInterface;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::postLoad)]
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class TranslatableEventSubscriber implements EventSubscriber
{
    /**
     * Entities persisted in a non-default locale before any Tuuid was assigned.
     *
     * The orphan verdict is deferred to flush time: prePersist auto-generates a
     * Tuuid below, and a translate() call later in the same flush clones it onto
     * the new locale variants — at persist time the entity only *looks* orphaned.
     *
     * @var \WeakMap<TranslatableInterface, true>
     */
    private \WeakMap $pendingOrphans;

    public function __construct(
        #[Autowire(param: 'tmi_translation.default_locale')]
        private string $defaultLocale,
        private EntityTranslatorInterface $entityTranslator,
        private LoggerInterface|null $logger = null,
        #[Autowire(param: 'tmi_translation.strict_orphan_check')]
        private bool $strictOrphanCheck = false,
    ) {
        $this->pendingOrphans = new \WeakMap();
    }

    /**
     * @return list<string>
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::postLoad,
            Events::onFlush,
        ];
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof TranslatableInterface) {
            return;
        }

        $locale = $entity->getLocale();

        // Orphan smell: a non-default locale with no shared Tuuid is almost
        // always application code that bypassed EntityTranslator::translate().
        // The verdict is deferred to onFlush — see $pendingOrphans.
        if (
            !$entity->hasTuuid()
            && null    !== $locale
            && ''      !== $locale
            && $locale !== $this->defaultLocale
        ) {
            $this->pendingOrphans[$entity] = true;
        }

        $entity->generateTuuid();

        if (null === $locale || '' === $locale) {
            $entity->setLocale($this->defaultLocale);
        }
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof TranslatableInterface) {
            return;
        }

        if (null === $entity->getLocale() || '' === $entity->getLocale()) {
            $entity->setLocale($this->defaultLocale);
        }

        $this->entityTranslator->afterLoad($entity);
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $uow           = $entityManager->getUnitOfWork();

        $insertions = $uow->getScheduledEntityInsertions();

        $this->reportOrphansAmong($insertions);

        foreach ($insertions as $entity) {
            if ($entity instanceof TranslatableInterface) {
                $this->entityTranslator->beforePersist($entity);
                $meta = $entityManager->getClassMetadata($entity::class);
                $uow->recomputeSingleEntityChangeSet($meta, $entity);
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof TranslatableInterface) {
                $this->entityTranslator->beforeUpdate($entity);
                $meta = $entityManager->getClassMetadata($entity::class);
                $uow->recomputeSingleEntityChangeSet($meta, $entity);
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof TranslatableInterface) {
                $this->entityTranslator->beforeRemove($entity);
            }
        }
    }

    /**
     * Settles the orphan verdict for entities flagged at persist time that are
     * part of this flush: an entity whose auto-generated Tuuid was adopted by
     * another insertion (translate() ran in the same flush) is linked, not
     * orphaned. Runs before the translator hooks so strict mode fails fast.
     *
     * @param array<object> $insertions
     */
    private function reportOrphansAmong(array $insertions): void
    {
        foreach ($insertions as $entity) {
            if (!$entity instanceof TranslatableInterface || !isset($this->pendingOrphans[$entity])) {
                continue;
            }

            unset($this->pendingOrphans[$entity]);

            $locale = $entity->getLocale();

            // The locale may have been corrected between persist() and flush().
            if (null === $locale || '' === $locale || $locale === $this->defaultLocale) {
                continue;
            }

            if ($this->tuuidIsSharedWithin($insertions, $entity)) {
                continue;
            }

            $this->reportOrphan($entity::class, $locale);
        }
    }

    /**
     * Whether another entity in the same flush carries this entity's Tuuid.
     *
     * A flagged entity received a freshly generated Tuuid at persist time and
     * Tuuids are immutable, so the only way it can be linked is other entities
     * cloned from it — which are always new, i.e. scheduled insertions.
     *
     * @param array<object> $insertions
     */
    private function tuuidIsSharedWithin(array $insertions, TranslatableInterface $entity): bool
    {
        $tuuid = (string) $entity->getTuuid();

        foreach ($insertions as $other) {
            if ($other === $entity || !$other instanceof TranslatableInterface) {
                continue;
            }

            if ($other->hasTuuid() && (string) $other->getTuuid() === $tuuid) {
                return true;
            }
        }

        return false;
    }

    /**
     * Surfaces an orphaned translation: throw in strict mode, otherwise warn.
     */
    private function reportOrphan(string $class, string $locale): void
    {
        if ($this->strictOrphanCheck) {
            throw OrphanTranslationException::forEntity($class, $locale);
        }

        $this->logger?->warning(
            'Translatable {class} flushed in non-default locale "{locale}" without a shared Tuuid '
            .'— no other locale variant links to it. Use EntityTranslator::translate() to create '
            .'linked translations, or run tmi:translation:doctor to audit existing rows.',
            ['class' => $class, 'locale' => $locale],
        );
    }
}
