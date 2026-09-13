<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Command;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * The mutable state of one `tmi:translation:sync-shared` run: the two run-wide
 * lists of values that differed but could not be written (readonly properties,
 * translation root references) and the per-class drift table the report renders.
 * Created by execute() and handed down the call chain, so no method needs a
 * by-reference parameter and nothing survives on the command instance between runs.
 *
 * @internal
 *
 * @phpstan-type SharedDrift array{tuuids: array<string, true>, rows: int, readonly: bool}
 */
final class SyncSharedRun
{
    /**
     * Readonly shared values that differ from the source and were left untouched.
     *
     * @var list<string>
     */
    public array $readonlyDrift = [];

    /**
     * Translation root references that differ between sibling rows -- reported,
     * never written.
     *
     * @var list<string>
     */
    public array $rootDrift = [];

    /**
     * The drift table of the class currently being walked, keyed by property path
     * so writable and readonly drift on the same property share one row; distinct
     * tuuids are counted separately from rows so a property shared across many
     * siblings of the same record is not overcounted. Reset per class.
     *
     * @var array<string, SharedDrift>
     */
    public array $drift = [];

    public function beginClass(): void
    {
        $this->drift = [];
    }

    /**
     * A value that differed from the source but is not this command's to write:
     * a readonly property, or a root reference (`$root`).
     */
    public function noteUnwritable(TranslatableInterface $sibling, string $path, bool $root): void
    {
        $line = \sprintf('%s::$%s (tuuid %s, locale %s)', $sibling::class, $path, (string) $sibling->getTuuid(), $sibling->getLocale() ?? 'none');

        if ($root) {
            $this->rootDrift[] = $line;
        } else {
            $this->readonlyDrift[] = $line;
        }

        $this->recordDrift($path, $sibling, true);
    }

    /** Records one drifted (property, sibling row) pair into the per-class drift table. */
    public function recordDrift(string $path, TranslatableInterface $sibling, bool $readonly): void
    {
        $entry = $this->drift[$path] ?? ['tuuids' => [], 'rows' => 0, 'readonly' => $readonly];

        $entry['tuuids'][(string) $sibling->getTuuid()] = true;
        ++$entry['rows'];
        $entry['readonly'] = $readonly;

        $this->drift[$path] = $entry;
    }

    /** Whether anything differed that this command must leave to the operator. */
    public function hasUnwritable(): bool
    {
        return [] !== $this->readonlyDrift || [] !== $this->rootDrift;
    }

    /**
     * The per-class drift table as rows for SymfonyStyle::table(), most rows first.
     *
     * @return list<list<string>>
     */
    public function driftRows(): array
    {
        $rows = [];

        foreach ($this->drift as $property => $entry) {
            $rows[] = [$property, \count($entry['tuuids']), $entry['rows'], $entry['readonly']];
        }

        usort($rows, static fn (array $a, array $b): int => $b[2] <=> $a[2]);

        return array_map(
            static fn (array $r): array => [$r[0], (string) $r[1], (string) $r[2], $r[3] ? 'no' : 'yes'],
            $rows,
        );
    }
}
