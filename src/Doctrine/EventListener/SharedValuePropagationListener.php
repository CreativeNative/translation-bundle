<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use Psr\Log\LoggerInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\SharedValueSynchronizer;
use Tmi\TranslationBundle\Exception\SharedValueConflictException;

/**
 * Opt-in flush-time invariant for #[SharedAmongstTranslations]: when
 * `tmi_translation.propagate_shared_on_flush` is enabled, a change to a shared
 * property on ANY locale variant reaches every other variant of the same
 * Tuuid inside the same `flush()`, whatever code performed the edit.
 *
 * Always registered -- `$enabled` decides at runtime whether `onFlush()` does
 * anything, so toggling the config option needs no service redefinition
 * (same shape as {@see LocaleVariantRemovalListener}).
 *
 * Mechanics, all inside Doctrine's documented `onFlush` rules (no `flush()`,
 * no `persist()` for the propagated rows -- only change-set recomputation):
 *
 * 1. Iterate over a SNAPSHOT of the scheduled updates: propagating schedules
 *    siblings for update, which would otherwise grow the set mid-loop.
 * 2. For each translatable update, intersect its change set with the class's
 *    shared change-set paths ({@see SharedValueSynchronizer::sharedProperties()}).
 *    Paths this listener itself wrote onto the entity earlier in the flush
 *    are excluded -- that is the ping-pong guard, tracked per (entity, path)
 *    rather than per entity so two variants that each edit a DIFFERENT
 *    shared property in one flush both get propagated.
 * 3. Siblings are the other variants in the database (via the synchronizer's
 *    filter-suspended lookup) PLUS any variant of the same Tuuid scheduled
 *    for INSERTION in this flush: a variant created by `translate()` earlier
 *    in the request carries the shared values the source had at clone time,
 *    and a query cannot see it yet. It receives the update like any sibling,
 *    so the new row is created from the updated source.
 * 4. Conflict rule: a sibling that is itself scheduled for update with a
 *    DIFFERENT new value for the same shared path throws
 *    {@see SharedValueConflictException} before anything is written -- never
 *    last-wins. Only update-scheduled siblings can conflict; an inserted
 *    variant's change set is its whole row, so a clone-time value cannot be
 *    told apart from a deliberate edit and the updated source wins.
 * 5. Every sibling that received a value goes through
 *    `UnitOfWork::recomputeSingleEntityChangeSet()` -- it merges into an
 *    existing change set (a sibling already scheduled with an unrelated
 *    change keeps that change), creates and schedules one for a managed
 *    sibling that had none, and merges into an insertion's change set too.
 *
 * With `enable_logging: true`, one `debug` line per propagating entity.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class SharedValuePropagationListener
{
    public function __construct(
        private readonly SharedValueSynchronizer $synchronizer,
        private readonly bool $enabled,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!$this->enabled) {
            return;
        }

        $entityManager = $args->getObjectManager();
        $uow           = $entityManager->getUnitOfWork();

        $updates    = $uow->getScheduledEntityUpdates();
        $insertions = $uow->getScheduledEntityInsertions();

        /**
         * Change-set paths this listener has written onto an entity in this
         * flush, keyed by the entity -- see step 2 in the class docblock.
         *
         * @var \SplObjectStorage<object, array<string, true>> $received
         */
        $received = new \SplObjectStorage();

        foreach ($updates as $entity) {
            if (!$entity instanceof TranslatableInterface) {
                continue;
            }

            $shared = $this->synchronizer->sharedProperties($entity::class);

            if ([] === $shared) {
                continue;
            }

            $changeSet       = $uow->getEntityChangeSet($entity);
            $alreadyReceived = $received->contains($entity) ? $received[$entity] : [];

            /** @var array<string, true> $changedPaths */
            $changedPaths = [];
            /** @var list<string> $selected */
            $selected = [];

            foreach ($shared as $property) {
                $hit = false;

                foreach ($property['changeSetPaths'] as $path) {
                    if (isset($changeSet[$path]) && !isset($alreadyReceived[$path])) {
                        $changedPaths[$path] = true;
                        $hit                 = true;
                    }
                }

                if ($hit) {
                    $selected[] = $property['path'];
                }
            }

            if ([] === $changedPaths) {
                continue;
            }

            $metadata = $entityManager->getClassMetadata($entity::class);
            $siblings = [
                ...$this->synchronizer->siblingsOf($entity),
                ...$this->scheduledSiblings($entityManager, $insertions, $entity, $metadata->rootEntityName),
            ];

            $this->assertNoConflict($uow, $metadata->getName(), $entity, $changeSet, array_keys($changedPaths), $siblings, $received);

            $touched = 0;

            foreach ($siblings as $sibling) {
                $report = $this->synchronizer->sync($entity, $sibling, $selected);

                // Mark even a sibling that already held the value: the path is settled for
                // it, so it must not propagate the same value back around the ring.
                $received[$sibling] = ($received->contains($sibling) ? $received[$sibling] : []) + $changedPaths;

                if ($report->hasChanges()) {
                    $uow->recomputeSingleEntityChangeSet($entityManager->getClassMetadata($sibling::class), $sibling);
                    ++$touched;
                }
            }

            $this->logger?->debug('[TMI Translation] Propagated shared values to sibling locale variants', [
                'class'      => $metadata->getName(),
                'tuuid'      => (string) $entity->getTuuid(),
                'locale'     => $entity->getLocale(),
                'properties' => array_keys($changedPaths),
                'siblings'   => $touched,
            ]);
        }
    }

    /**
     * Variants of the source's Tuuid that only exist in this flush's
     * insertions -- invisible to a database lookup, see step 3.
     *
     * @param array<int, object> $insertions
     * @param class-string       $rootEntityName
     *
     * @return list<TranslatableInterface>
     */
    private function scheduledSiblings(EntityManagerInterface $entityManager, array $insertions, TranslatableInterface $source, string $rootEntityName): array
    {
        $tuuid    = (string) $source->getTuuid();
        $siblings = [];

        foreach ($insertions as $candidate) {
            if (
                !$candidate instanceof TranslatableInterface
                || (string) $candidate->getTuuid() !== $tuuid
                || $candidate->getLocale() === $source->getLocale()
                || $entityManager->getClassMetadata($candidate::class)->rootEntityName !== $rootEntityName
            ) {
                continue;
            }

            $siblings[] = $candidate;
        }

        return $siblings;
    }

    /**
     * Step 4: refuse to overwrite a sibling's own, different, new value.
     *
     * @param class-string                                                                  $class
     * @param array<string, array{mixed, mixed}|PersistentCollection<array-key, object>> $changeSet
     * @param list<string>                                                                  $paths
     * @param list<TranslatableInterface>                                                   $siblings
     * @param \SplObjectStorage<object, array<string, true>>                                $received
     */
    private function assertNoConflict(UnitOfWork $uow, string $class, TranslatableInterface $source, array $changeSet, array $paths, array $siblings, \SplObjectStorage $received): void
    {
        foreach ($siblings as $sibling) {
            if (!$uow->isScheduledForUpdate($sibling)) {
                continue;
            }

            $siblingChangeSet = $uow->getEntityChangeSet($sibling);
            $siblingReceived  = $received->contains($sibling) ? $received[$sibling] : [];

            foreach ($paths as $path) {
                if (!isset($siblingChangeSet[$path]) || isset($siblingReceived[$path])) {
                    continue;
                }

                $mine   = self::newValue($changeSet[$path]);
                $theirs = self::newValue($siblingChangeSet[$path]);

                if (SharedValueSynchronizer::valuesEqual($mine, $theirs)) {
                    continue;
                }

                throw SharedValueConflictException::forProperty($class, (string) $source->getTuuid(), $path, $source->getLocale() ?? '', $mine, $sibling->getLocale() ?? '', $theirs);
            }
        }
    }

    /**
     * The new value of one change-set entry. A shared path is never a
     * collection (collections cannot be shared), so the PersistentCollection
     * shape of a change-set entry is only ever matched to keep the types
     * honest.
     *
     * @param array{mixed, mixed}|PersistentCollection<array-key, object> $change
     */
    private static function newValue(array|PersistentCollection $change): mixed
    {
        return $change instanceof PersistentCollection ? $change : $change[1];
    }
}
