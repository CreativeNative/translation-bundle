<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\CacheWarmer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Mapping\FieldMapping;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\CacheWarmer\TranslatableEntityValidationWarmer;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\Translatable;

#[CoversClass(TranslatableEntityValidationWarmer::class)]
final class TranslatableEntityValidationWarmerTest extends TestCase
{
    public function testIsOptionalReturnsFalse(): void
    {
        $em     = self::createStub(EntityManagerInterface::class);
        $warmer = new TranslatableEntityValidationWarmer($em);

        self::assertFalse($warmer->isOptional());
    }

    public function testWarmUpPassesForEntitiesWithNoUniqueConstraints(): void
    {
        $metadata = $this->createTranslatableMetadata(Translatable::class);

        // Add regular fields without unique constraint
        $titleField                       = new FieldMapping(type: 'string', fieldName: 'title', columnName: 'title');
        $metadata->fieldMappings['title'] = $titleField;

        $localeField                       = new FieldMapping(type: 'string', fieldName: 'locale', columnName: 'locale');
        $metadata->fieldMappings['locale'] = $localeField;

        $warmer = $this->createWarmer([$metadata]);
        $result = $warmer->warmUp('/tmp/cache');

        self::assertSame([], $result);
    }

    public function testWarmUpSkipsNonTranslatableEntities(): void
    {
        $metadata = new ClassMetadata(\stdClass::class);

        // Add a unique field to a non-translatable entity
        $uniqueField                      = new FieldMapping(type: 'string', fieldName: 'email', columnName: 'email');
        $uniqueField->unique              = true;
        $metadata->fieldMappings['email'] = $uniqueField;

        $warmer = $this->createWarmer([$metadata]);
        $result = $warmer->warmUp('/tmp/cache');

        // Should pass - non-translatable entities are ignored
        self::assertSame([], $result);
    }

    public function testWarmUpDetectsSingleColumnUniqueField(): void
    {
        $metadata = $this->createTranslatableMetadata(Translatable::class);

        // Add a unique field
        $slugField                       = new FieldMapping(type: 'string', fieldName: 'slug', columnName: 'slug');
        $slugField->unique               = true;
        $metadata->fieldMappings['slug'] = $slugField;

        $warmer = $this->createWarmer([$metadata]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('TMI Translation Bundle: Unique constraint validation failed with 1 error(s):');
        $this->expectExceptionMessage('Entity "Tmi\TranslationBundle\Fixtures\Entity\Embedded\Translatable"');
        $this->expectExceptionMessage('field "slug" has a single-column unique constraint');
        $this->expectExceptionMessage('unique values must be scoped per locale');
        $this->expectExceptionMessage('#[ORM\UniqueConstraint(name: "uniq_translatable_slug_locale", fields: ["slug", "locale"])]');

        $warmer->warmUp('/tmp/cache');
    }

    public function testWarmUpSkipsSystemFields(): void
    {
        $metadata = $this->createTranslatableMetadata(Translatable::class);

        // Add unique constraints on system fields - should be allowed
        $idField                       = new FieldMapping(type: 'integer', fieldName: 'id', columnName: 'id');
        $idField->unique               = true;
        $metadata->fieldMappings['id'] = $idField;

        $tuuidField                       = new FieldMapping(type: 'string', fieldName: 'tuuid', columnName: 'tuuid');
        $tuuidField->unique               = true;
        $metadata->fieldMappings['tuuid'] = $tuuidField;

        $localeField                       = new FieldMapping(type: 'string', fieldName: 'locale', columnName: 'locale');
        $localeField->unique               = true;
        $metadata->fieldMappings['locale'] = $localeField;

        $warmer = $this->createWarmer([$metadata]);
        $result = $warmer->warmUp('/tmp/cache');

        // Should pass - system fields are excluded
        self::assertSame([], $result);
    }

    public function testWarmUpDetectsTableLevelUniqueConstraintMissingLocale(): void
    {
        $metadata = $this->createTranslatableMetadata(Translatable::class);

        // Add table-level unique constraint without locale
        $metadata->table['uniqueConstraints'] = [
            'uniq_slug' => [
                'fields' => ['slug'],
            ],
        ];

        $warmer = $this->createWarmer([$metadata]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('TMI Translation Bundle: Unique constraint validation failed with 1 error(s):');
        $this->expectExceptionMessage('Entity "Tmi\TranslationBundle\Fixtures\Entity\Embedded\Translatable"');
        $this->expectExceptionMessage('unique constraint "uniq_slug" on fields ["slug"] does not include the locale column');
        $this->expectExceptionMessage('#[ORM\UniqueConstraint(name: "uniq_slug", fields: ["slug","locale"])]');

        $warmer->warmUp('/tmp/cache');
    }

    public function testWarmUpPassesForUniqueConstraintWithLocale(): void
    {
        $metadata = $this->createTranslatableMetadata(Translatable::class);

        // Add table-level unique constraint WITH locale
        $metadata->table['uniqueConstraints'] = [
            'uniq_slug_locale' => [
                'fields' => ['slug', 'locale'],
            ],
        ];

        $warmer = $this->createWarmer([$metadata]);
        $result = $warmer->warmUp('/tmp/cache');

        // Should pass - locale is included
        self::assertSame([], $result);
    }

    public function testWarmUpCollectsMultipleErrors(): void
    {
        $metadata = $this->createTranslatableMetadata(Translatable::class);

        // Add both field-level unique AND table-level unique without locale
        $slugField                       = new FieldMapping(type: 'string', fieldName: 'slug', columnName: 'slug');
        $slugField->unique               = true;
        $metadata->fieldMappings['slug'] = $slugField;

        $metadata->table['uniqueConstraints'] = [
            'uniq_email' => [
                'fields' => ['email'],
            ],
        ];

        $warmer = $this->createWarmer([$metadata]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('TMI Translation Bundle: Unique constraint validation failed with 2 error(s):');
        $this->expectExceptionMessage('field "slug" has a single-column unique constraint');
        $this->expectExceptionMessage('unique constraint "uniq_email" on fields ["email"] does not include the locale column');

        $warmer->warmUp('/tmp/cache');
    }

    public function testWarmUpReturnsEmptyArray(): void
    {
        $metadata = $this->createTranslatableMetadata(Translatable::class);
        $warmer   = $this->createWarmer([$metadata]);

        $result = $warmer->warmUp('/tmp/cache');

        self::assertEmpty($result);
    }

    public function testWarmUpHandlesBuildDirParameter(): void
    {
        $metadata = $this->createTranslatableMetadata(Translatable::class);
        $warmer   = $this->createWarmer([$metadata]);

        // Test with buildDir parameter (Symfony 6.4+)
        $result = $warmer->warmUp('/tmp/cache', '/tmp/build');

        self::assertSame([], $result);
    }

    public function testWarmUpSkipsSystemOnlyTableConstraints(): void
    {
        $metadata = $this->createTranslatableMetadata(Translatable::class);

        // Add table-level constraint on system field only - should be allowed
        $metadata->table['uniqueConstraints'] = [
            'uniq_id' => [
                'fields' => ['id'],
            ],
            'uniq_tuuid' => [
                'fields' => ['tuuid'],
            ],
            'uniq_locale' => [
                'fields' => ['locale'],
            ],
        ];

        $warmer = $this->createWarmer([$metadata]);
        $result = $warmer->warmUp('/tmp/cache');

        // Should pass - system-only constraints are excluded
        self::assertSame([], $result);
    }

    public function testWarmUpHandlesColumnsInsteadOfFields(): void
    {
        $metadata = $this->createTranslatableMetadata(Translatable::class);

        // Some metadata uses 'columns' instead of 'fields'
        $metadata->table['uniqueConstraints'] = [
            'uniq_slug' => [
                'columns' => ['slug'],
            ],
        ];

        $warmer = $this->createWarmer([$metadata]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('unique constraint "uniq_slug" on fields ["slug"] does not include the locale column');

        $warmer->warmUp('/tmp/cache');
    }

    public function testWarmUpHandlesEmptyConstraintFields(): void
    {
        $metadata = $this->createTranslatableMetadata(Translatable::class);

        // Edge case: empty constraint definition
        $metadata->table['uniqueConstraints'] = [
            'uniq_empty' => [],
        ];

        $warmer = $this->createWarmer([$metadata]);
        $result = $warmer->warmUp('/tmp/cache');

        // Should pass - empty constraint is skipped
        self::assertSame([], $result);
    }

    /**
     * @param class-string $className
     *
     * @return ClassMetadata<object>
     */
    private function createTranslatableMetadata(string $className): ClassMetadata
    {
        /** @var ClassMetadata<object> $metadata */
        $metadata        = new ClassMetadata($className);
        $metadata->table = ['name' => 'test_table'];

        return $metadata;
    }

    /**
     * @param list<ClassMetadata<object>> $allMetadata
     */
    private function createWarmer(array $allMetadata): TranslatableEntityValidationWarmer
    {
        $factory = self::createStub(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willReturn($allMetadata);

        $em = self::createStub(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($factory);

        return new TranslatableEntityValidationWarmer($em);
    }
}
