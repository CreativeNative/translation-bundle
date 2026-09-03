<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Repository;

use Doctrine\ORM\EntityRepository;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * Provides locale variant query helpers for translatable entity repositories.
 *
 * Thin delegation to {@see LocaleVariantFinder} — see there for the
 * filter-suspension mechanics.
 *
 * @phpstan-require-extends EntityRepository
 */
trait TranslatableRepositoryTrait
{
    /**
     * All locale variants for a Tuuid, keyed by locale.
     *
     * @return array<string, TranslatableInterface>
     */
    public function findAllLocaleVariants(Tuuid $tuuid): array
    {
        return $this->findAllLocaleVariantsBatch([$tuuid])[(string) $tuuid] ?? [];
    }

    /**
     * Batch: all locale variants for multiple Tuuids.
     *
     * @param list<Tuuid> $tuuids
     *
     * @return array<string, array<string, TranslatableInterface>> tuuid string => locale => entity
     */
    public function findAllLocaleVariantsBatch(array $tuuids): array
    {
        return new LocaleVariantFinder($this->getEntityManager())
            ->findAllLocaleVariantsBatch($this->getEntityName(), $tuuids);
    }
}
