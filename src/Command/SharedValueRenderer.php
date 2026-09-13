<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Tmi\TranslationBundle\Utils\ReflectionHelper;

/**
 * One-line renderings of shared values for `tmi:translation:sync-shared -v`.
 *
 * Scalars as literals, enums by case, dates in ATOM, arrays as JSON, a managed entity
 * as `ShortClass#id`, any other object (an embeddable, a value object) as
 * `ShortClass{prop: value, ...}` one level deep; every line is cut at 60 characters.
 * Lives next to the command because it is the command's display concern:
 * `SharedValueSynchronizer` hands over raw values and stays free of Doctrine display
 * knowledge.
 *
 * @internal
 */
final readonly class SharedValueRenderer
{
    private const int MAX_LENGTH = 60;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function render(mixed $value): string
    {
        if (null === $value) {
            return 'null';
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_string($value)) {
            return '"'.self::truncate($value).'"';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof \UnitEnum) {
            return new \ReflectionEnum($value)->getShortName().'::'.$value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (\is_array($value)) {
            return self::truncate(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        \assert(\is_object($value), 'null, scalars, enums, dates and arrays are handled above');

        $class = ReflectionHelper::realClass($value);
        $short = new \ReflectionClass($class)->getShortName();

        if (!$this->entityManager->getMetadataFactory()->isTransient($class)) {
            $ids = array_map(
                static fn (mixed $id): string => $id instanceof \Stringable || \is_scalar($id) ? (string) $id : get_debug_type($id),
                $this->entityManager->getClassMetadata($class)->getIdentifierValues($value),
            );

            return $short.'#'.implode(',', $ids);
        }

        $parts = [];
        foreach (new \ReflectionObject($value)->getProperties() as $property) {
            $inner   = $property->isInitialized($value) ? $property->getValue($value) : null;
            $parts[] = $property->name.': '.(\is_object($inner) && !$inner instanceof \UnitEnum && !$inner instanceof \DateTimeInterface
                ? new \ReflectionClass(ReflectionHelper::realClass($inner))->getShortName()
                : $this->render($inner));
        }

        return self::truncate($short.'{'.implode(', ', $parts).'}');
    }

    private static function truncate(string $text): string
    {
        // ext-mbstring is not a bundle requirement; the /u regex cuts on a character boundary.
        return \strlen($text) > self::MAX_LENGTH
            ? (string) preg_replace('/^(.{'.(self::MAX_LENGTH - 1).'}).+$/us', '$1…', $text)
            : $text;
    }
}
