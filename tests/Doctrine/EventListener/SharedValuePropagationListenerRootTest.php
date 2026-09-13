<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\EventListener;

use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\CoversClass;
use Tmi\TranslationBundle\Doctrine\EventListener\SharedValuePropagationListener;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\SharedValueSynchronizer;
use Tmi\TranslationBundle\Fixtures\Entity\Root\EstateA;
use Tmi\TranslationBundle\Fixtures\Entity\Root\ListingA;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * The flush-time propagation and a translation root reference (5.1): re-pointing one
 * row's root is an identity decision the listener neither propagates nor conflict-checks.
 * Negative proof: without the `root` skip, the listener would intersect the change set
 * with the `listing` path, sync it onto the sibling, and the reloaded sibling below
 * would carry the new root.
 */
#[CoversClass(SharedValuePropagationListener::class)]
final class SharedValuePropagationListenerRootTest extends IntegrationTestCase
{
    public function testRePointingOneRowsRootReachesNoSiblingAndThrowsNoConflict(): void
    {
        $synchronizer = self::getContainer()->get('test.shared_value_synchronizer');
        self::assertInstanceOf(SharedValueSynchronizer::class, $synchronizer);
        $this->entityManager()->getEventManager()->addEventListener(Events::onFlush, new SharedValuePropagationListener($synchronizer, true));

        $tuuid = Tuuid::generate();
        $root  = new ListingA();
        $root->adoptTuuid($tuuid);
        $other = new ListingA();
        $other->adoptTuuid(Tuuid::generate());

        $en = new EstateA()->setTuuid($tuuid)->setLocale('en_US')->setListing($root)->setFamily('fam');
        $de = new EstateA()->setTuuid($tuuid)->setLocale('de_DE')->setListing($root)->setFamily('fam');

        foreach ([$root, $other, $en, $de] as $entity) {
            $this->entityManager()->persist($entity);
        }
        $this->entityManager()->flush();

        // One flush: the root reference moves on en_US, a genuinely shared column moves too.
        $en->setListing($other)->setFamily('renamed');
        $this->entityManager()->flush();

        $deId = $de->getId();
        self::assertNotNull($deId);
        $this->entityManager()->clear();

        $reloadedDe = new LocaleVariantFinder($this->entityManager())->withoutLocaleFilter(fn (): object|null => $this->entityManager()->find(EstateA::class, $deId));
        self::assertInstanceOf(EstateA::class, $reloadedDe);

        self::assertSame('renamed', $reloadedDe->getFamily(), 'the shared column propagates as always');
        self::assertNotNull($reloadedDe->getListing());
        self::assertSame($root->getId(), $reloadedDe->getListing()->getId(), 'the root reference is never propagated');
    }
}
