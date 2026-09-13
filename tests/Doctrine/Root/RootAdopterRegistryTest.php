<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\Root;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Doctrine\Root\RootAdopterRegistry;
use Tmi\TranslationBundle\Fixtures\Entity\Root\Article;
use Tmi\TranslationBundle\Fixtures\Entity\Root\Estate;
use Tmi\TranslationBundle\Fixtures\Entity\Root\EstateB;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\Support\Root\ArticleRootAdopter;
use Tmi\TranslationBundle\Test\Support\Root\EstateRootAdopter;

#[CoversClass(RootAdopterRegistry::class)]
final class RootAdopterRegistryTest extends TestCase
{
    public function testAllIsOrderedByTheClassServed(): void
    {
        $registry = new RootAdopterRegistry();
        $estate   = new EstateRootAdopter();
        $article  = new ArticleRootAdopter();

        $registry->addAdopter($estate, Estate::class);
        $registry->addAdopter($article, Article::class);

        self::assertSame([$article, $estate], $registry->all());
    }

    public function testAdopterForResolvesAConcreteLeafToItsHierarchysAdopter(): void
    {
        $registry = new RootAdopterRegistry();
        $estate   = new EstateRootAdopter();
        $registry->addAdopter($estate, Estate::class);

        self::assertSame($estate, $registry->adopterFor(Estate::class));
        self::assertSame($estate, $registry->adopterFor(EstateB::class));
        self::assertNull($registry->adopterFor(Scalar::class));
    }

    public function testAnAdopterWhoseTagDisagreesWithItsMethodIsRefused(): void
    {
        $registry = new RootAdopterRegistry();

        self::expectException(\LogicException::class);
        self::expectExceptionMessage(\sprintf('is tagged for %s but getTranslatableClass() returns %s', Article::class, Estate::class));

        $registry->addAdopter(new EstateRootAdopter(), Article::class);
    }
}
