<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Repository;

use Doctrine\ORM\EntityRepository;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * Provides locale variant query helpers for translatable entity repositories.
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
        if ([] === $tuuids) {
            return [];
        }

        $em      = $this->getEntityManager();
        $filters = $em->getFilters();

        $wasEnabled = $filters->has('tmi_translation_locale_filter')
            && $filters->isEnabled('tmi_translation_locale_filter');

        if ($wasEnabled) {
            $filters->disable('tmi_translation_locale_filter');
        }

        try {
            $tuuidStrings = array_map(static fn (Tuuid $t): string => (string) $t, $tuuids);

            /** @var list<TranslatableInterface> $results */
            $results = $em->createQueryBuilder()
                ->select('t')
                ->from($this->getEntityName(), 't')
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
        } finally {
            if ($wasEnabled) {
                $filters->enable('tmi_translation_locale_filter');
            }
        }
    }
}
