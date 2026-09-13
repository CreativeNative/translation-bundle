<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Support\Root;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;
use Tmi\TranslationBundle\Doctrine\Root\RootAdopterInterface;

/**
 * Decorates an adopter so a single test can misbehave on purpose: a factory that
 * mints, a factory of the wrong class, an attach() that throws mid-group, a getRoot()
 * that hands back a root without identity. Every hook left null delegates.
 */
final class OverridableRootAdopter implements RootAdopterInterface
{
    /**
     * @param (\Closure(non-empty-list<TranslatableInterface>): TranslationRootInterface)|null                 $createRootFor
     * @param (\Closure(TranslatableInterface, TranslationRootInterface, RootAdopterInterface): void)|null    $attach
     * @param (\Closure(TranslatableInterface): (TranslationRootInterface|null))|null                          $getRoot
     */
    public function __construct(
        private readonly RootAdopterInterface $inner,
        private readonly \Closure|null $createRootFor = null,
        private readonly \Closure|null $attach = null,
        private readonly \Closure|null $getRoot = null,
    ) {
    }

    public function getTranslatableClass(): string
    {
        return $this->inner->getTranslatableClass();
    }

    public function getRoot(TranslatableInterface $row): TranslationRootInterface|null
    {
        return null !== $this->getRoot ? ($this->getRoot)($row) : $this->inner->getRoot($row);
    }

    public function createRootFor(array $group): TranslationRootInterface
    {
        return null !== $this->createRootFor ? ($this->createRootFor)($group) : $this->inner->createRootFor($group);
    }

    public function attach(TranslatableInterface $row, TranslationRootInterface $root): void
    {
        if (null !== $this->attach) {
            ($this->attach)($row, $root, $this->inner);

            return;
        }

        $this->inner->attach($row, $root);
    }

    public function rootClassFor(TranslatableInterface $row): string
    {
        return $this->inner->rootClassFor($row);
    }

    public function coherenceKey(TranslatableInterface $row): string
    {
        return $this->inner->coherenceKey($row);
    }
}
