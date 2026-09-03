<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * Cross-locale lookups for translatable entities.
 *
 * A locale-filtered query only ever sees the current request's locale, so
 * finding "every variant of this Tuuid" needs the filter suspended for the
 * duration of the query — {@see withoutLocaleFilter()} is what every method
 * here, and every other place in the bundle that needs the same thing
 * ({@see Repository\TranslatableRepositoryTrait},
 * {@see \Tmi\TranslationBundle\Translation\LocaleCompletenessResolver}), builds on.
 */
final readonly class LocaleVariantFinder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * All locale variants for a Tuuid, keyed by locale.
     *
     * @param class-string $class
     *
     * @return array<string, TranslatableInterface>
     */
    public function findAllLocaleVariants(string $class, Tuuid $tuuid): array
    {
        return $this->findAllLocaleVariantsBatch($class, [$tuuid])[(string) $tuuid] ?? [];
    }

    /**
     * Batch: all locale variants for multiple Tuuids, in a single query.
     *
     * @param class-string $class
     * @param list<Tuuid>                         $tuuids
     *
     * @return array<string, array<string, TranslatableInterface>> tuuid string => locale => entity
     */
    public function findAllLocaleVariantsBatch(string $class, array $tuuids): array
    {
        if ([] === $tuuids) {
            return [];
        }

        return $this->withoutLocaleFilter(function () use ($class, $tuuids): array {
            $tuuidStrings = array_map(static fn (Tuuid $tuuid): string => (string) $tuuid, $tuuids);

            /** @var list<TranslatableInterface> $results */
            $results = $this->entityManager->createQueryBuilder()
                ->select('t')
                ->from($class, 't')
                ->where('t.tuuid IN (:tuuids)')
                ->setParameter('tuuids', $tuuidStrings)
                ->getQuery()
                ->getResult();

            /** @var array<string, array<string, TranslatableInterface>> $grouped */
            $grouped = [];

            foreach ($results as $entity) {
                $grouped[(string) $entity->getTuuid()][$entity->getLocale() ?? ''] = $entity;
            }

            return $grouped;
        });
    }

    /**
     * A single Tuuid's variant in one locale, or null when it does not exist.
     *
     * @param class-string $class
     */
    public function findLocaleVariant(string $class, Tuuid $tuuid, string $locale): TranslatableInterface|null
    {
        return $this->findLocaleVariantsBatch($class, [(string) $tuuid], $locale)[0] ?? null;
    }

    /**
     * Batch: the given locale's variant for each Tuuid that has one, in a
     * single query. Tuuids without a variant in `$locale` are simply absent
     * from the result — the caller cannot tell which ones were skipped from
     * the return value alone.
     *
     * @param class-string $class
     * @param list<string>                        $tuuids
     *
     * @return list<TranslatableInterface>
     */
    public function findLocaleVariantsBatch(string $class, array $tuuids, string $locale): array
    {
        if ([] === $tuuids) {
            return [];
        }

        return $this->withoutLocaleFilter(function () use ($class, $tuuids, $locale): array {
            /** @var list<TranslatableInterface> */
            return $this->entityManager->createQueryBuilder()
                ->select('t')
                ->from($class, 't')
                ->where('t.tuuid IN (:tuuids)')
                ->andWhere('t.locale = :locale')
                ->setParameter('tuuids', $tuuids)
                ->setParameter('locale', $locale)
                ->getQuery()
                ->getResult();
        });
    }

    /**
     * Runs `$query` with the locale filter disabled, restoring it to exactly
     * the state it was in before — enabled or not — even if `$query` throws.
     *
     * @template T
     *
     * @param callable(): T $query
     *
     * @return T
     */
    public function withoutLocaleFilter(callable $query): mixed
    {
        $filters = $this->entityManager->getFilters();

        $wasEnabled = $filters->has(LocaleFilter::NAME) && $filters->isEnabled(LocaleFilter::NAME);

        if ($wasEnabled) {
            $filters->disable(LocaleFilter::NAME);
        }

        try {
            return $query();
        } finally {
            if ($wasEnabled) {
                $filters->enable(LocaleFilter::NAME);
            }
        }
    }
}
