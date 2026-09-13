<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Support\Root;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;
use Tmi\TranslationBundle\Doctrine\Root\RootAdopterInterface;
use Tmi\TranslationBundle\Fixtures\Entity\Root\Estate;
use Tmi\TranslationBundle\Fixtures\Entity\Root\EstateA;
use Tmi\TranslationBundle\Fixtures\Entity\Root\Listing;
use Tmi\TranslationBundle\Fixtures\Entity\Root\ListingA;
use Tmi\TranslationBundle\Fixtures\Entity\Root\ListingB;

/**
 * The reference adopter for the {@see Estate} SINGLE_TABLE hierarchy: the STI
 * decision (EstateA rows imply a ListingA, EstateB rows a ListingB) and the family
 * as the coherence key. Registered in TestKernel with tag
 * `tmi_translation.root_adopter`, `class: Estate`.
 */
final class EstateRootAdopter implements RootAdopterInterface
{
    public function getTranslatableClass(): string
    {
        return Estate::class;
    }

    public function getRoot(TranslatableInterface $row): TranslationRootInterface|null
    {
        \assert($row instanceof Estate);

        return $row->getListing();
    }

    public function createRootFor(array $group): TranslationRootInterface
    {
        $first = $group[0];
        \assert($first instanceof Estate);

        $class = $this->rootClassFor($first);

        return new $class($first->getFamily());
    }

    public function attach(TranslatableInterface $row, TranslationRootInterface $root): void
    {
        \assert($row instanceof Estate && $root instanceof Listing);

        $row->setListing($root);
    }

    public function rootClassFor(TranslatableInterface $row): string
    {
        return $row instanceof EstateA ? ListingA::class : ListingB::class;
    }

    public function coherenceKey(TranslatableInterface $row): string
    {
        \assert($row instanceof Estate);

        return $row->getFamily();
    }
}
