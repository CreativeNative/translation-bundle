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
    public function __construct(
        #[Autowire(param: 'tmi_translation.default_locale')]
        private string $defaultLocale,
        private EntityTranslatorInterface $entityTranslator,
        private LoggerInterface|null $logger = null,
        #[Autowire(param: 'tmi_translation.strict_orphan_check')]
        private bool $strictOrphanCheck = false,
    ) {
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
        if (
            !$entity->hasTuuid()
            && null    !== $locale
            && ''      !== $locale
            && $locale !== $this->defaultLocale
        ) {
            $this->reportOrphan($entity::class, $locale);
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

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
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
     * Surfaces an orphaned translation: throw in strict mode, otherwise warn.
     */
    private function reportOrphan(string $class, string $locale): void
    {
        if ($this->strictOrphanCheck) {
            throw OrphanTranslationException::forEntity($class, $locale);
        }

        $this->logger?->warning(
            'Translatable {class} persisted in non-default locale "{locale}" without a shared Tuuid '
            .'— created as a standalone entity. Use EntityTranslator::translate() to link it.',
            ['class' => $class, 'locale' => $locale],
        );
    }
}
