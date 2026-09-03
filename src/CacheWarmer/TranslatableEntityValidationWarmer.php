<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\CacheWarmer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

final class TranslatableEntityValidationWarmer implements CacheWarmerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function isOptional(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public function warmUp(string $cacheDir, string|null $buildDir = null): array
    {
        /** @var list<string> $errors */
        $errors      = [];
        $allMetadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        /** @var array<string, true> $checkedTables */
        $checkedTables = [];

        foreach ($allMetadata as $metadata) {
            // A mapped superclass has no table of its own — $metadata->table would
            // be a guessed default, not a real column set to validate.
            if ($metadata->isMappedSuperclass) {
                continue;
            }

            $reflection = new \ReflectionClass($metadata->getName());

            if (!$reflection->implementsInterface(TranslatableInterface::class)) {
                continue;
            }

            $this->validateFieldUniqueConstraints($metadata, $errors);

            // A SINGLE_TABLE subclass's rows live in its root's physical table, so
            // its table-level unique constraints are the exact same declaration
            // already validated for the root (or an earlier sibling) — checking
            // again would just repeat the same error once per subclass. A JOINED
            // subclass owns a genuinely separate table and is validated on its
            // own pass below, same as an unrelated entity.
            $tableName = $metadata->getTableName();

            if (isset($checkedTables[$tableName])) {
                continue;
            }

            $checkedTables[$tableName] = true;

            $this->validateTableUniqueConstraints($metadata, $errors);
        }

        if ([] !== $errors) {
            throw new \LogicException(sprintf("TMI Translation Bundle: Unique constraint validation failed with %d error(s):\n\n%s", count($errors), implode("\n\n", $errors)));
        }

        return [];
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<string> $errors
     */
    private function validateFieldUniqueConstraints(ClassMetadata $metadata, array &$errors): void
    {
        $className = $metadata->getName();

        foreach ($metadata->fieldMappings as $fieldName => $fieldMapping) {
            // Skip system fields that are legitimately unique
            if (in_array($fieldName, ['id', 'tuuid', 'locale'], true)) {
                continue;
            }

            // An inheritance hierarchy hydrates every concrete subclass's metadata
            // with the FULL field set, own and inherited, so a field declared on
            // an ancestor ENTITY (STI or JOINED — not a mapped superclass, whose
            // fields Doctrine never marks "inherited") already went through this
            // check against the class that actually declares it.
            if ($metadata->isInheritedField($fieldName)) {
                continue;
            }

            if (true === $fieldMapping->unique) {
                $errors[] = sprintf(
                    'Entity "%s": field "%s" has a single-column unique constraint. '
                    .'For translatable entities, unique values must be scoped per locale. '
                    .'Replace `unique: true` with a composite unique constraint: '
                    .'#[ORM\UniqueConstraint(name: "uniq_%s_%s_locale", fields: ["%s", "locale"])]',
                    $className,
                    $fieldName,
                    $this->toSnakeCase($this->getShortClassName($className)),
                    $this->toSnakeCase($fieldName),
                    $fieldName,
                );
            }
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<string> $errors
     */
    private function validateTableUniqueConstraints(ClassMetadata $metadata, array &$errors): void
    {
        $className = $metadata->getName();

        /** @var array<string, array{fields?: list<string>, columns?: list<string>, options?: array<string, mixed>}> $uniqueConstraints */
        $uniqueConstraints = $metadata->table['uniqueConstraints'] ?? [];
        foreach ($uniqueConstraints as $constraintName => $constraint) {
            /** @var list<string> $fields */
            $fields = $constraint['fields'] ?? $constraint['columns'] ?? [];

            // Skip empty constraint definitions
            if ([] === $fields) {
                continue;
            }

            // Skip if locale already included
            if (in_array('locale', $fields, true)) {
                continue;
            }

            // Skip system-only constraints
            if (1 === count($fields) && in_array($fields[0], ['id', 'tuuid', 'locale'], true)) {
                continue;
            }

            $errors[] = sprintf(
                'Entity "%s": unique constraint "%s" on fields %s does not include the locale column. '
                .'For translatable entities, add "locale" to the constraint fields: '
                .'#[ORM\UniqueConstraint(name: "%s", fields: %s)]',
                $className,
                $constraintName,
                json_encode($fields, JSON_THROW_ON_ERROR),
                $constraintName,
                json_encode(array_merge($fields, ['locale']), JSON_THROW_ON_ERROR),
            );
        }
    }

    private function getShortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }

    private function toSnakeCase(string $camelCase): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $camelCase));
    }
}
