<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;

/**
 * The flush/detach cycle of a command that streams a table with
 * {@see LocaleVariantFinder::streamGroupedByTuuid()}: entities are settled as
 * their Tuuid group completes, and every {@see SIZE} groups the batch is flushed
 * (write mode only) and every settled entity detached. Peak memory stays a small,
 * table-size-independent multiple of the locale count, and a group is never split
 * across a flush -- the counter ticks per group, never per row.
 *
 * Detaching is deliberately per entity, never a blanket EntityManager::clear():
 * the stream has already hydrated the NEXT group's first row (the "lookahead")
 * by the time a group is yielded, and that row has not been processed yet.
 * clear() would detach it too, and a property write to a detached entity is
 * invisible to every later flush(), so whichever group landed on a batch
 * boundary would silently lose its update. Only entities handed to settle() --
 * whose group is done -- are ever detached.
 */
final class GroupBatch
{
    /** Tuuid groups per flush/detach cycle. */
    public const int SIZE = 10;

    /** @var list<object> */
    private array $settled = [];

    private int $groups = 0;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly bool $flush,
    ) {
    }

    /**
     * Marks entities whose group is fully processed; they are detached at the
     * end of the current batch.
     *
     * @param iterable<object> $entities
     */
    public function settle(iterable $entities): void
    {
        foreach ($entities as $entity) {
            $this->settled[] = $entity;
        }
    }

    /** One group done; runs the cycle once {@see SIZE} groups have accumulated. */
    public function tick(): void
    {
        if (++$this->groups >= self::SIZE) {
            $this->finish();
        }
    }

    /**
     * Flushes (write mode) and detaches everything settled so far -- the trailing
     * partial batch at the end of a stream, or a full one from tick().
     */
    public function finish(): void
    {
        if ($this->flush) {
            $this->entityManager->flush();
        }

        foreach ($this->settled as $entity) {
            $this->entityManager->detach($entity);
        }

        $this->settled = [];
        $this->groups  = 0;
    }
}
