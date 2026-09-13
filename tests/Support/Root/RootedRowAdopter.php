<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Support\Root;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;
use Tmi\TranslationBundle\Doctrine\Root\RootAdopterInterface;
use Tmi\TranslationBundle\Fixtures\Entity\Bughunt\RootedRow;
use Tmi\TranslationBundle\Fixtures\Entity\Bughunt\RowRoot;

/**
 * The adopter for the {@see RootedRow} fixture (the SPEC § 4 shape, a root reference
 * with `inversedBy`). Rows are constructed with their root, so every group is
 * `complete`; it exists because the compile-time cross-check requires exactly one
 * adopter per root-declaring class.
 */
final class RootedRowAdopter implements RootAdopterInterface
{
    #[\Override]
    public function getTranslatableClass(): string
    {
        return RootedRow::class;
    }

    #[\Override]
    public function getRoot(TranslatableInterface $row): TranslationRootInterface|null
    {
        \assert($row instanceof RootedRow);

        $property = new \ReflectionProperty(RootedRow::class, 'root');

        return $property->isInitialized($row) ? $row->getRoot() : null;
    }

    #[\Override]
    public function createRootFor(array $group): TranslationRootInterface
    {
        return new RowRoot();
    }

    #[\Override]
    public function attach(TranslatableInterface $row, TranslationRootInterface $root): void
    {
        new \ReflectionProperty(RootedRow::class, 'root')->setValue($row, $root);
    }

    #[\Override]
    public function rootClassFor(TranslatableInterface $row): string
    {
        return RowRoot::class;
    }

    #[\Override]
    public function coherenceKey(TranslatableInterface $row): string
    {
        return '';
    }
}
