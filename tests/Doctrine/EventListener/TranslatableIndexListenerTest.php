<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Tmi\TranslationBundle\Doctrine\EventListener\TranslatableIndexListener;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\NonTranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Test\IntegrationTestCase;

final class TranslatableIndexListenerTest extends IntegrationTestCase
{
    public function testInjectsPlainIndexForTranslatableEntities(): void
    {
        // The DI-registered listener runs while metadata is loaded.
        $metadata  = $this->entityManager()->getClassMetadata(Scalar::class);
        $indexName = TranslatableIndexListener::indexName($metadata->getTableName());

        self::assertSame(['tuuid', 'locale'], $this->columnsOf($metadata, 'indexes', $indexName));
    }

    public function testInjectsUniqueConstraintWhenConfigured(): void
    {
        $metadata  = $this->entityManager()->getClassMetadata(Scalar::class);
        $indexName = TranslatableIndexListener::indexName($metadata->getTableName());

        new TranslatableIndexListener(true)->loadClassMetadata($this->args($metadata));

        self::assertSame(['tuuid', 'locale'], $this->columnsOf($metadata, 'uniqueConstraints', $indexName));
    }

    public function testSkipsWhenIndexAlreadyDeclared(): void
    {
        $metadata = $this->entityManager()->getClassMetadata(Scalar::class);

        // Index already present from the DI listener — a second pass is a no-op.
        new TranslatableIndexListener(false)->loadClassMetadata($this->args($metadata));

        $indexes = $metadata->table['indexes'] ?? [];
        self::assertCount(1, $indexes);
    }

    public function testSkipsMappedSuperclass(): void
    {
        $metadata                     = $this->entityManager()->getClassMetadata(Scalar::class);
        $metadata->table['indexes']   = [];
        $metadata->isMappedSuperclass = true;

        new TranslatableIndexListener(false)->loadClassMetadata($this->args($metadata));

        self::assertSame([], $metadata->table['indexes']);
    }

    public function testSkipsInheritanceChild(): void
    {
        $metadata                   = $this->entityManager()->getClassMetadata(Scalar::class);
        $metadata->table['indexes'] = [];
        $metadata->rootEntityName   = \stdClass::class;

        new TranslatableIndexListener(false)->loadClassMetadata($this->args($metadata));

        self::assertSame([], $metadata->table['indexes']);
    }

    public function testSkipsNonTranslatableEntity(): void
    {
        $metadata  = $this->entityManager()->getClassMetadata(NonTranslatableManyToOneBidirectionalChild::class);
        $indexName = TranslatableIndexListener::indexName($metadata->getTableName());

        new TranslatableIndexListener(false)->loadClassMetadata($this->args($metadata));

        self::assertArrayNotHasKey($indexName, $metadata->table['indexes'] ?? []);
    }

    public function testSkipsTranslatableEntityWithoutMappedFields(): void
    {
        // Implements TranslatableInterface but has no tuuid/locale field mapped.
        $metadata = new ClassMetadata(Scalar::class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        $metadata->rootEntityName = Scalar::class;

        new TranslatableIndexListener(false)->loadClassMetadata($this->args($metadata));

        self::assertArrayNotHasKey('indexes', $metadata->table);
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return mixed The 'columns' entry of the named index/constraint
     */
    private function columnsOf(ClassMetadata $metadata, string $key, string $indexName): mixed
    {
        $bucket = $metadata->table[$key] ?? [];
        self::assertIsArray($bucket);
        self::assertArrayHasKey($indexName, $bucket);

        $index = $bucket[$indexName];
        self::assertIsArray($index);
        self::assertArrayHasKey('columns', $index);

        return $index['columns'];
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function args(ClassMetadata $metadata): LoadClassMetadataEventArgs
    {
        return new LoadClassMetadataEventArgs($metadata, $this->entityManager());
    }
}
