<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Tmi\TranslationBundle\Fixtures\Entity\Root\Article;
use Tmi\TranslationBundle\Fixtures\Entity\Root\ArticleRoot;
use Tmi\TranslationBundle\Fixtures\Entity\Root\EstateA;
use Tmi\TranslationBundle\Fixtures\Entity\Root\ListingA;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * R1 through translate() (5.1): the clone's root reference is the IDENTICAL root
 * instance -- with the optional marker (Estate) and without it (Article), nullable
 * (phase 1) and non-nullable (phase 2). Negative proof: before 5.1 the same walk fell
 * into DoctrineObjectHandler's `clone $data` on the root (the non-translatable-object
 * clone path), handing the variant a copy of the root with a NULL id -- the
 * assertNotSame() in each test is what the old code produced.
 */
final class TranslationRootTranslateTest extends IntegrationTestCase
{
    public function testTheCloneKeepsTheIdenticalRootWithTheMarker(): void
    {
        $root = new ListingA('fam');
        $root->mintTuuid();
        $row = new EstateA()->setTuuid($root->getTuuid())->setLocale('en_US')->setListing($root)->setTitle('EN');

        $this->entityManager()->persist($root);
        $this->entityManager()->persist($row);
        $this->entityManager()->flush();

        $translated = $this->translator()->translate($row, self::TARGET_LOCALE);

        self::assertInstanceOf(EstateA::class, $translated);
        self::assertIsTranslation($row, $translated, self::TARGET_LOCALE);
        self::assertSame($root, $translated->getListing(), 'the root is reaffirmed to identity, never cloned');
        self::assertSame('EN', $translated->getTitle(), 'copy_source: true copies the translatable title');
    }

    public function testTheCloneKeepsTheIdenticalRootWithoutTheMarkerAndPersists(): void
    {
        $root = ArticleRoot::mint();
        $row  = new Article($root)->setLocale('en_US')->setTitle('EN');

        $this->entityManager()->persist($root);
        $this->entityManager()->persist($row);
        $this->entityManager()->flush();

        $translated = $this->translator()->translateAndPersist($row, self::TARGET_LOCALE);
        $this->entityManager()->flush();

        self::assertInstanceOf(Article::class, $translated);
        self::assertSame($root, $translated->getRoot());
        self::assertSame((string) $root->getTuuid(), (string) $translated->getTuuid());
        self::assertNotNull($translated->getId());
        self::assertNotSame($row->getId(), $translated->getId());

        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(Article::class, $translated->getId());
        self::assertInstanceOf(Article::class, $reloaded);
        self::assertSame($root->getId(), $reloaded->getRoot()->getId());
    }

    public function testANullRootReferenceStaysNullOnTheClone(): void
    {
        // Phase 1: a row not yet adopted. Nothing to reaffirm; nothing minted either.
        $row = new EstateA()->setTuuid(Tuuid::generate())->setLocale('en_US');
        $this->entityManager()->persist($row);
        $this->entityManager()->flush();

        $translated = $this->translator()->translate($row, self::TARGET_LOCALE);

        self::assertInstanceOf(EstateA::class, $translated);
        self::assertNull($translated->getListing());
    }

    public function testTheConstructorCopiesTheRootsIdentityAndNeverMints(): void
    {
        $root = ArticleRoot::mint();
        $row  = new Article($root);

        self::assertTrue($row->hasTuuid());
        self::assertSame((string) $root->getTuuid(), (string) $row->getTuuid());
    }
}
