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
        return false;
    }

    /**
     * @return list<string>
     */
    public function warmUp(string $cacheDir, string|null $buildDir = null): array
    {
        /** @var list<string> $errors */
        $errors = [];
        $allMetadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        foreach ($allMetadata as $metadata) {
            $reflection = new \ReflectionClass($metadata->getName());

            if (!$reflection->implementsInterface(TranslatableInterface::class)) {
                continue;
            }

            $this->validateUniqueConstraints($metadata, $errors);
        }

        if ([] !== $errors) {
            throw new \LogicException(sprintf(
                "TMI Translation Bundle: Unique constraint validation failed with %d error(s):\n\n%s",
                count($errors),
                implode("\n\n", $errors),
            ));
        }

        return [];
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<string> $errors
     */
    private function validateUniqueConstraints(ClassMetadata $metadata, array &$errors): void
    {
        $className = $metadata->getName();

        // Field-level unique: true check
        foreach ($metadata->fieldMappings as $fieldName => $fieldMapping) {
            // Skip system fields that are legitimately unique
            if (in_array($fieldName, ['id', 'tuuid', 'locale'], true)) {
                continue;
            }

            if (true === $fieldMapping->unique) {
                $errors[] = sprintf(
                    'Entity "%s": field "%s" has a single-column unique constraint. '
                    . 'For translatable entities, unique values must be scoped per locale. '
                    . 'Replace `unique: true` with a composite unique constraint: '
                    . '#[ORM\UniqueConstraint(name: "uniq_%s_%s_locale", fields: ["%s", "locale"])]',
                    $className,
                    $fieldName,
                    $this->toSnakeCase($this->getShortClassName($className)),
                    $this->toSnakeCase($fieldName),
                    $fieldName,
                );
            }
        }

        // Table-level unique constraints check
        /** @var array<string, array{fields?: list<string>, columns?: list<string>, options?: array<string, mixed>}> $uniqueConstraints */
        $uniqueConstraints = $metadata->table['uniqueConstraints'] ?? [];
        foreach ($uniqueConstraints as $constraintName => $constraint) {
            /** @var list<string> $fields */
            $fields = $constraint['fields'] ?? $constraint['columns'] ?? [];

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
                . 'For translatable entities, add "locale" to the constraint fields: '
                . '#[ORM\UniqueConstraint(name: "%s", fields: %s)]',
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
