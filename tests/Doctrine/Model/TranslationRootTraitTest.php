<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\Model;

use Doctrine\ORM\Mapping\Column;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootTrait;
use Tmi\TranslationBundle\Doctrine\Type\TuuidType;
use Tmi\TranslationBundle\Fixtures\Entity\Root\Listing;
use Tmi\TranslationBundle\Fixtures\Entity\Root\ListingA;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * The root's identity contract (5.1): minted once or adopted once, never lazily,
 * never re-identified -- and the one Doctrine trap the trait exists to avoid.
 */
#[CoversTrait(TranslationRootTrait::class)]
final class TranslationRootTraitTest extends IntegrationTestCase
{
    public function testANewRootHasNoTuuidAndGetTuuidNeverLazilyMints(): void
    {
        $root = new ListingA();

        self::assertFalse($root->hasTuuid());

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('never mints one lazily');

        $root->getTuuid();
    }

    public function testMintGivesABrandNewIdentityExactlyOnce(): void
    {
        $root = new ListingA();
        $root->mintTuuid();

        self::assertTrue($root->hasTuuid());
        $first = $root->getTuuid();

        self::expectException(\LogicException::class);
        self::expectExceptionMessage(sprintf('already carries tuuid %s', $first));

        $root->mintTuuid();
    }

    public function testAdoptTakesOverAnExistingIdentity(): void
    {
        $tuuid = Tuuid::generate();
        $root  = new ListingA();
        $root->adoptTuuid($tuuid);

        self::assertTrue($root->hasTuuid());
        self::assertSame($tuuid, $root->getTuuid());
    }

    public function testAdoptingTheSameValueAgainIsANoOp(): void
    {
        $tuuid = Tuuid::generate();
        $root  = new ListingA();
        $root->adoptTuuid($tuuid);

        // A logically equal but distinct instance -- what Doctrine hands back on hydration.
        $root->adoptTuuid(new Tuuid((string) $tuuid));

        self::assertTrue($root->getTuuid()->equals($tuuid));
    }

    public function testAdoptingADifferentValueThrows(): void
    {
        $root = new ListingA();
        $root->adoptTuuid(Tuuid::generate());

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('cannot adopt');

        $root->adoptTuuid(Tuuid::generate());
    }

    public function testMintAfterAdoptThrows(): void
    {
        $root = new ListingA();
        $root->adoptTuuid(Tuuid::generate());

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('identified exactly once');

        $root->mintTuuid();
    }

    public function testTheRootImplementsTheInterfaceButIsNotTranslatable(): void
    {
        $interfaces = class_implements(ListingA::class);

        self::assertIsArray($interfaces);
        self::assertContains(TranslationRootInterface::class, $interfaces);
        self::assertNotContains(TranslatableInterface::class, $interfaces, 'a root has no locale; the translatable listeners must never run for it');
    }

    public function testTheTuuidColumnIsUniqueNotNullAndOfTheTuuidType(): void
    {
        $property   = new \ReflectionProperty(Listing::class, 'tuuid');
        $attributes = $property->getAttributes(Column::class);

        self::assertCount(1, $attributes);

        $column = $attributes[0]->newInstance();

        self::assertSame(TuuidType::NAME, $column->type);
        self::assertSame(36, $column->length);
        self::assertFalse($column->nullable);
        self::assertTrue($column->unique, 'One root per identity: the column must be unique.');
        self::assertFalse($property->isReadOnly(), 'Not PHP readonly: Doctrine\'s ReflectionReadonlyProperty compares by identity, and a re-hydrated equal Tuuid would throw.');
    }

    /**
     * The readonly trap, pinned: refresh() re-hydrates the row into the SAME managed
     * instance, handing the trait a new, logically equal Tuuid instance.
     */
    public function testAPersistedRootSurvivesRefreshAndReHydrationInAFreshEntityManager(): void
    {
        $root = new ListingA('fam');
        $root->mintTuuid();
        $tuuid = (string) $root->getTuuid();

        $this->entityManager()->persist($root);
        $this->entityManager()->flush();

        $this->entityManager()->refresh($root);
        self::assertSame($tuuid, (string) $root->getTuuid());

        $id = $root->getId();
        self::assertNotNull($id);
        $this->entityManager()->clear();

        $reloaded = $this->entityManager()->find(ListingA::class, $id);
        self::assertInstanceOf(ListingA::class, $reloaded);
        self::assertTrue($reloaded->hasTuuid());
        self::assertSame($tuuid, (string) $reloaded->getTuuid());
        self::assertSame('fam', $reloaded->getFamily());
    }

    public function testTheUniqueColumnRefusesASecondRootWithTheSameIdentity(): void
    {
        $tuuid = Tuuid::generate();

        $first = new ListingA();
        $first->adoptTuuid($tuuid);
        $second = new ListingA();
        $second->adoptTuuid($tuuid);

        $this->entityManager()->persist($first);
        $this->entityManager()->persist($second);

        self::expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

        $this->entityManager()->flush();
    }
}
