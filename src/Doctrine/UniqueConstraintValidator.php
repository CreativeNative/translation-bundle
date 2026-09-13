<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * The two unique-constraint rules a translatable entity's mapping must obey.
 *
 * Every locale variant is a full row, so a value that is unique per object legitimately
 * repeats once per locale: a single-column `unique: true`, or a table-level unique
 * constraint without the locale column, would make the second translation fail at
 * INSERT. Both are reported here with the composite constraint that fixes them.
 *
 * Pure: takes one class's metadata, returns its errors. {@see EventListener\UniqueConstraintListener}
 * runs it at `loadClassMetadata`, once per class, and decides what to skip.
 */
final readonly class UniqueConstraintValidator
{
    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return list<string> one message per violation, empty when the mapping is clean
     */
    public function validate(ClassMetadata $metadata): array
    {
        $errors = $this->fieldErrors($metadata);

        // A SINGLE_TABLE subclass's rows live in its root's physical table, so its
        // table-level constraints are the root's own declaration mirrored onto it --
        // checking them again would report one bad mapping once per subclass. A JOINED
        // subclass owns a separate table and is checked on its own.
        if ($metadata->rootEntityName === $metadata->name || $metadata->isInheritanceTypeJoined()) {
            $errors = [...$errors, ...$this->tableErrors($metadata)];
        }

        return $errors;
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return list<string>
     */
    private function fieldErrors(ClassMetadata $metadata): array
    {
        $errors    = [];
        $className = $metadata->getName();

        foreach ($metadata->fieldMappings as $fieldName => $fieldMapping) {
            if (true !== $fieldMapping->unique) {
                continue;
            }

            // The identifier and the two system columns are legitimately unique.
            if ($metadata->isIdentifier($fieldName) || \in_array($fieldName, TranslatableInterface::SYSTEM_PROPERTIES, true)) {
                continue;
            }

            // An inheritance hierarchy hydrates every concrete subclass's metadata with
            // the FULL field set, own and inherited, so a field declared on an ancestor
            // ENTITY (STI or JOINED -- not a mapped superclass, whose fields Doctrine
            // never marks "inherited") is checked once, against the class declaring it.
            if ($metadata->isInheritedField($fieldName)) {
                continue;
            }

            $errors[] = \sprintf(
                'Entity "%s": field "%s" has a single-column unique constraint. '
                .'For translatable entities, unique values must be scoped per locale. '
                .'Replace `unique: true` with a composite unique constraint: '
                .'#[ORM\UniqueConstraint(name: "uniq_%s_%s_locale", fields: ["%s", "locale"])]',
                $className,
                $fieldName,
                $this->toSnakeCase(new \ReflectionClass($className)->getShortName()),
                $this->toSnakeCase($fieldName),
                $fieldName,
            );
        }

        return $errors;
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return list<string>
     */
    private function tableErrors(ClassMetadata $metadata): array
    {
        $errors    = [];
        $className = $metadata->getName();

        /** @var array<string, array{fields?: list<string>, columns?: list<string>, options?: array<string, mixed>}> $uniqueConstraints */
        $uniqueConstraints = $metadata->table['uniqueConstraints'] ?? [];

        foreach ($uniqueConstraints as $constraintName => $constraint) {
            /** @var list<string> $fields */
            $fields = $constraint['fields'] ?? $constraint['columns'] ?? [];

            if ([] === $fields || \in_array('locale', $fields, true)) {
                continue;
            }

            // A constraint on one system column alone (the (tuuid, locale) one the
            // index listener injects carries locale and is skipped above).
            if (1 === \count($fields) && \in_array($fields[0], ['id', ...TranslatableInterface::SYSTEM_PROPERTIES], true)) {
                continue;
            }

            $errors[] = \sprintf(
                'Entity "%s": unique constraint "%s" on fields %s does not include the locale column. '
                .'For translatable entities, add "locale" to the constraint fields: '
                .'#[ORM\UniqueConstraint(name: "%s", fields: %s)]',
                $className,
                $constraintName,
                json_encode($fields, JSON_THROW_ON_ERROR),
                $constraintName,
                json_encode([...$fields, 'locale'], JSON_THROW_ON_ERROR),
            );
        }

        return $errors;
    }

    private function toSnakeCase(string $camelCase): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $camelCase));
    }
}
