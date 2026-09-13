<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Doctrine\UniqueConstraintValidator;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\Translatable;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti\StiBook;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti\StiRoot;

/**
 * The cases of the former TranslatableEntityValidationWarmer test, one class's metadata
 * at a time -- which is how the listener now calls the validator.
 */
#[CoversClass(UniqueConstraintValidator::class)]
final class UniqueConstraintValidatorTest extends TestCase
{
    private UniqueConstraintValidator $validator;

    #[\Override]
    protected function setUp(): void
    {
        $this->validator = new UniqueConstraintValidator();
    }

    public function testPassesForEntitiesWithNoUniqueConstraints(): void
    {
        $metadata = $this->translatableMetadata(Translatable::class);
        $this->addField($metadata, 'title');
        $this->addField($metadata, 'locale');

        self::assertSame([], $this->validator->validate($metadata));
    }

    public function testDetectsASingleColumnUniqueField(): void
    {
        $metadata = $this->translatableMetadata(Translatable::class);
        $this->addField($metadata, 'slug', unique: true);

        $errors = $this->validator->validate($metadata);

        self::assertCount(1, $errors);
        self::assertStringContainsString('Entity "Tmi\TranslationBundle\Fixtures\Entity\Embedded\Translatable"', $errors[0]);
        self::assertStringContainsString('field "slug" has a single-column unique constraint', $errors[0]);
        self::assertStringContainsString('unique values must be scoped per locale', $errors[0]);
        self::assertStringContainsString('#[ORM\UniqueConstraint(name: "uniq_translatable_slug_locale", fields: ["slug", "locale"])]', $errors[0]);
    }

    public function testTheSuggestedConstraintNameUsesTheShortClassNameInSnakeCase(): void
    {
        $metadata = $this->translatableMetadata(StiBook::class);
        $this->addField($metadata, 'isbnNumber', unique: true);

        $errors = $this->validator->validate($metadata);

        self::assertCount(1, $errors);
        self::assertStringContainsString('name: "uniq_sti_book_isbn_number_locale"', $errors[0]);
    }

    public function testSkipsTheIdentifierAndTheSystemFields(): void
    {
        $metadata             = $this->translatableMetadata(Translatable::class);
        $metadata->identifier = ['id'];
        $this->addField($metadata, 'id', unique: true, type: 'integer');
        $this->addField($metadata, 'tuuid', unique: true);
        $this->addField($metadata, 'locale', unique: true);

        self::assertSame([], $this->validator->validate($metadata));
    }

    public function testDetectsATableLevelUniqueConstraintMissingLocale(): void
    {
        $metadata                             = $this->translatableMetadata(Translatable::class);
        $metadata->table['uniqueConstraints'] = ['uniq_slug' => ['fields' => ['slug']]];

        $errors = $this->validator->validate($metadata);

        self::assertCount(1, $errors);
        self::assertStringContainsString('Entity "Tmi\TranslationBundle\Fixtures\Entity\Embedded\Translatable"', $errors[0]);
        self::assertStringContainsString('unique constraint "uniq_slug" on fields ["slug"] does not include the locale column', $errors[0]);
        self::assertStringContainsString('#[ORM\UniqueConstraint(name: "uniq_slug", fields: ["slug","locale"])]', $errors[0]);
    }

    public function testPassesForAUniqueConstraintWithLocale(): void
    {
        $metadata                             = $this->translatableMetadata(Translatable::class);
        $metadata->table['uniqueConstraints'] = ['uniq_slug_locale' => ['fields' => ['slug', 'locale']]];

        self::assertSame([], $this->validator->validate($metadata));
    }

    public function testCollectsFieldAndTableErrorsTogether(): void
    {
        $metadata = $this->translatableMetadata(Translatable::class);
        $this->addField($metadata, 'slug', unique: true);
        $metadata->table['uniqueConstraints'] = ['uniq_email' => ['fields' => ['email']]];

        $errors = $this->validator->validate($metadata);

        self::assertCount(2, $errors);
        self::assertStringContainsString('field "slug" has a single-column unique constraint', $errors[0]);
        self::assertStringContainsString('unique constraint "uniq_email" on fields ["email"] does not include the locale column', $errors[1]);
    }

    public function testSkipsSystemOnlyTableConstraints(): void
    {
        $metadata                             = $this->translatableMetadata(Translatable::class);
        $metadata->table['uniqueConstraints'] = [
            'uniq_id'     => ['fields' => ['id']],
            'uniq_tuuid'  => ['fields' => ['tuuid']],
            'uniq_locale' => ['fields' => ['locale']],
        ];

        self::assertSame([], $this->validator->validate($metadata));
    }

    public function testReadsColumnsWhenAConstraintHasNoFields(): void
    {
        $metadata                             = $this->translatableMetadata(Translatable::class);
        $metadata->table['uniqueConstraints'] = ['uniq_slug' => ['columns' => ['slug']]];

        $errors = $this->validator->validate($metadata);

        self::assertCount(1, $errors);
        self::assertStringContainsString('unique constraint "uniq_slug" on fields ["slug"] does not include the locale column', $errors[0]);
    }

    public function testSkipsAnEmptyConstraintDefinition(): void
    {
        $metadata                             = $this->translatableMetadata(Translatable::class);
        $metadata->table['uniqueConstraints'] = ['uniq_empty' => []];

        self::assertSame([], $this->validator->validate($metadata));
    }

    /**
     * An inheritance hierarchy hydrates every concrete subclass's metadata with the FULL
     * field set, own and inherited -- StiBook's metadata mirrors StiRoot's own "slug"
     * field, marked inherited. Without that check the same bad mapping would be reported
     * once per subclass.
     */
    public function testSkipsAnInheritedField(): void
    {
        $root = $this->translatableMetadata(StiRoot::class);
        $this->addField($root, 'slug', unique: true);

        $child = $this->stiChildMetadata();
        $this->addField($child, 'slug', unique: true, inheritedFrom: StiRoot::class);

        self::assertCount(1, $this->validator->validate($root));
        self::assertSame([], $this->validator->validate($child));
    }

    public function testChecksAFieldTheSubclassDeclaresItself(): void
    {
        $child = $this->stiChildMetadata();
        $this->addField($child, 'isbn', unique: true);

        $errors = $this->validator->validate($child);

        self::assertCount(1, $errors);
        self::assertStringContainsString('field "isbn" has a single-column unique constraint', $errors[0]);
    }

    /**
     * SINGLE_TABLE: StiBook's rows live in StiRoot's own physical table, so Doctrine
     * mirrors the SAME table-level constraint onto both metadata objects. Only the root
     * reports it.
     */
    public function testChecksTableLevelConstraintsOnTheRootOfASingleTableHierarchyOnly(): void
    {
        $root                             = $this->translatableMetadata(StiRoot::class);
        $root->table['uniqueConstraints'] = ['uniq_slug' => ['fields' => ['slug']]];

        $child                             = $this->stiChildMetadata();
        $child->table['uniqueConstraints'] = ['uniq_slug' => ['fields' => ['slug']]];

        self::assertCount(1, $this->validator->validate($root));
        self::assertSame([], $this->validator->validate($child));
    }

    /**
     * JOINED: StiBook owns a genuinely separate table from StiRoot, so its own table-level
     * constraint is distinct and is still reported.
     */
    public function testChecksTableLevelConstraintsOfAJoinedSubclass(): void
    {
        $child = $this->stiChildMetadata();
        $child->setInheritanceType(ClassMetadata::INHERITANCE_TYPE_JOINED);
        $child->table = ['name' => 'sti_book', 'uniqueConstraints' => ['uniq_book_isbn' => ['fields' => ['isbn']]]];

        $errors = $this->validator->validate($child);

        self::assertCount(1, $errors);
        self::assertStringContainsString('unique constraint "uniq_book_isbn"', $errors[0]);
    }

    /**
     * @param class-string $className
     *
     * @return ClassMetadata<object>
     */
    private function translatableMetadata(string $className): ClassMetadata
    {
        /** @var ClassMetadata<object> $metadata */
        $metadata        = new ClassMetadata($className);
        $metadata->table = ['name' => 'test_table'];

        return $metadata;
    }

    /**
     * @return ClassMetadata<object>
     */
    private function stiChildMetadata(): ClassMetadata
    {
        $child                 = $this->translatableMetadata(StiBook::class);
        $child->rootEntityName = StiRoot::class;
        $child->setInheritanceType(ClassMetadata::INHERITANCE_TYPE_SINGLE_TABLE);

        return $child;
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param class-string|null     $inheritedFrom
     */
    private function addField(ClassMetadata $metadata, string $name, bool $unique = false, string $type = 'string', string|null $inheritedFrom = null): void
    {
        $field         = new FieldMapping(type: $type, fieldName: $name, columnName: $name);
        $field->unique = $unique;
        if (null !== $inheritedFrom) {
            $field->inherited = $inheritedFrom;
        }

        $metadata->fieldMappings[$name] = $field;
    }
}
