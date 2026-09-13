<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Support\Root;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;
use Tmi\TranslationBundle\Doctrine\Root\RootAdopterInterface;
use Tmi\TranslationBundle\Fixtures\Entity\Root\Article;
use Tmi\TranslationBundle\Fixtures\Entity\Root\ArticleRoot;

/**
 * The adopter for the phase-2 {@see Article} fixture. Its rows are constructed with
 * their root, so in practice every group is `complete`; it exists because the
 * compile-time cross-check requires exactly one adopter per root-declaring class.
 * getRoot() reads the non-nullable property through reflection, tolerating an
 * uninitialized one (a row hydrated from a NULL FK during a migration window).
 */
final class ArticleRootAdopter implements RootAdopterInterface
{
    public function getTranslatableClass(): string
    {
        return Article::class;
    }

    public function getRoot(TranslatableInterface $row): TranslationRootInterface|null
    {
        \assert($row instanceof Article);

        $property = new \ReflectionProperty(Article::class, 'root');

        return $property->isInitialized($row) ? $row->getRoot() : null;
    }

    public function createRootFor(array $group): TranslationRootInterface
    {
        return new ArticleRoot();
    }

    public function attach(TranslatableInterface $row, TranslationRootInterface $root): void
    {
        new \ReflectionProperty(Article::class, 'root')->setValue($row, $root);
    }

    public function rootClassFor(TranslatableInterface $row): string
    {
        return ArticleRoot::class;
    }

    public function coherenceKey(TranslatableInterface $row): string
    {
        return '';
    }
}
