<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * Injects a composite (tuuid, locale) index into every translatable entity.
 *
 * A trait cannot declare a class-level #[ORM\Index], so {@see TranslatableTrait}
 * entities would otherwise ship with an unindexed `tuuid` column — turning every
 * locale-variant lookup into a full table scan.
 *
 * When `tmi_translation.unique_locale_variants` is enabled the index becomes a
 * UNIQUE constraint, which doubles as a DB-level guard against duplicate locale
 * rows for the same Tuuid.
 */
#[AsDoctrineListener(event: Events::loadClassMetadata)]
final readonly class TranslatableIndexListener
{
    /**
     * Index-name suffix. The table name is prepended so the name stays unique
     * database-wide (SQLite requires globally unique index names).
     */
    public const string INDEX_SUFFIX = '_tuuid_locale_idx';

    public function __construct(
        private bool $unique = false,
    ) {
    }

    /**
     * The composite index name injected for the given table.
     */
    public static function indexName(string $tableName): string
    {
        return $tableName.self::INDEX_SUFFIX;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $metadata = $args->getClassMetadata();

        // Mapped superclasses have no table of their own.
        if ($metadata->isMappedSuperclass) {
            return;
        }

        // For inheritance hierarchies only the root entity owns the table.
        if ($metadata->rootEntityName !== $metadata->name) {
            return;
        }

        if (!$metadata->getReflectionClass()->implementsInterface(TranslatableInterface::class)) {
            return;
        }

        // Defensive: an interface implementer might not use TranslatableTrait.
        if (!$metadata->hasField('tuuid') || !$metadata->hasField('locale')) {
            return;
        }

        $key       = $this->unique ? 'uniqueConstraints' : 'indexes';
        $indexName = self::indexName($metadata->getTableName());

        // Skip if already declared (idempotency / explicit user mapping).
        if (isset($metadata->table[$key][$indexName])) {
            return;
        }

        $metadata->table[$key][$indexName] = [
            'columns' => [
                $metadata->getColumnName('tuuid'),
                $metadata->getColumnName('locale'),
            ],
        ];
    }
}
