<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Fixtures\Entity\Bughunt\Link;
use Tmi\TranslationBundle\Fixtures\Entity\Bughunt\Node;
use Tmi\TranslationBundle\Fixtures\Entity\Bughunt\RootedRow;
use Tmi\TranslationBundle\Fixtures\Entity\Bughunt\RowRoot;
use Tmi\TranslationBundle\Fixtures\Entity\Bughunt\SeededStamp;
use Tmi\TranslationBundle\Fixtures\Entity\Bughunt\Stamp;
use Tmi\TranslationBundle\Fixtures\Enum\Priority;

/**
 * The two bugs the 5.2 bug hunt proved red on 5.1 (backlog #53, #54), plus the shapes
 * next to them that must keep working: value objects under the attribute cascade, and
 * self-referential associations in every to-one form.
 */
final class ValueObjectAndSelfReferenceTest extends IntegrationTestCase
{
    // ------------------------------------------------------------------
    // #53 -- value objects follow #[EmptyOnTranslate] and copy_source: false
    // ------------------------------------------------------------------

    /** Red on 5.1: only the string was emptied, the date and the enum were copied. */
    public function testEmptyOnTranslateEmptiesADateAnEnumAndAString(): void
    {
        $stamp = new Stamp();
        $stamp->setLocale('en_US')->setPublishedAt(new \DateTimeImmutable('2020-01-01'))->setPriority(Priority::High)->setNote('n');
        $this->entityManager()->persist($stamp);
        $this->entityManager()->flush();

        $translated = $this->translator()->translate($stamp, 'de_DE');
        self::assertInstanceOf(Stamp::class, $translated);

        self::assertNull($translated->getNote());
        self::assertNull($translated->getPublishedAt(), '#[EmptyOnTranslate] on a DateTimeImmutable');
        self::assertNull($translated->getPriority(), '#[EmptyOnTranslate] on an enum');

        // The source is untouched.
        self::assertSame(Priority::High, $stamp->getPriority());
        self::assertNotNull($stamp->getPublishedAt());
    }

    /**
     * Red on 5.1: every date was copied. Under copy_source: false a nullable value
     * object without an attribute is seeded null, a shared one is copied, and a
     * non-nullable one takes the documented safety fallback (copied from the source).
     */
    public function testCopySourceFalseSeedsANullableValueObjectNull(): void
    {
        $stamp = new SeededStamp();
        $stamp->setLocale('en_US')->setPublishedAt(new \DateTimeImmutable('2020-01-01'))->setApprovedAt(new \DateTimeImmutable('2021-01-01'));
        $this->entityManager()->persist($stamp);
        $this->entityManager()->flush();

        $translated = $this->translator()->translate($stamp, 'de_DE');
        self::assertInstanceOf(SeededStamp::class, $translated);

        self::assertNull($translated->getPublishedAt(), 'nullable, unshared: seeded empty');
        self::assertEquals(new \DateTimeImmutable('2021-01-01'), $translated->getApprovedAt(), 'shared: copied');
        self::assertEquals($stamp->getCreatedAt(), $translated->getCreatedAt(), 'non-nullable: the safety fallback copies it');
    }

    // ------------------------------------------------------------------
    // #54 -- a self-referential bidirectional ManyToOne tree
    // ------------------------------------------------------------------

    /** Red on 5.1: the translated root got its own child as parent -- a cycle. */
    public function testTranslatingAChildKeepsTheTranslatedRootARoot(): void
    {
        [$root, $child] = $this->persistTree();

        $childDe = $this->translator()->translate($child, 'de_DE');
        self::assertInstanceOf(Node::class, $childDe);

        $rootDe = $childDe->getParent();
        self::assertInstanceOf(Node::class, $rootDe);
        self::assertNotSame($root, $rootDe);
        self::assertSame('de_DE', $rootDe->getLocale());
        self::assertNull($rootDe->getParent(), 'the translated root must stay a root');
        self::assertSame((string) $root->getTuuid(), (string) $rootDe->getTuuid());

        // The root clone's inverse-side collection is walked while the child is still
        // mid-translation, so the cycle guard skips it (documented: complete after a
        // reload, never persisted from this side). What must never happen is the SOURCE
        // child landing in the clone's collection.
        self::assertFalse($rootDe->getChildren()->contains($child), 'the source child never lands in the root clone');
    }

    public function testTranslatingTheRootTranslatesTheChildrenOntoTheRootClone(): void
    {
        [$root, $child] = $this->persistTree();

        $rootDe = $this->translator()->translate($root, 'de_DE');
        self::assertInstanceOf(Node::class, $rootDe);

        self::assertNull($rootDe->getParent());
        self::assertCount(1, $rootDe->getChildren());

        $childDe = $rootDe->getChildren()->first();
        self::assertInstanceOf(Node::class, $childDe);
        self::assertNotSame($child, $childDe);
        self::assertSame('de_DE', $childDe->getLocale());
        self::assertSame($rootDe, $childDe->getParent(), 'the child clone points at the root clone');
        self::assertSame((string) $child->getTuuid(), (string) $childDe->getTuuid());
    }

    /**
     * The data-corruption case: the root already has a de_DE row in the database. Red
     * on 5.1: translating the child re-pointed that existing row's parent_id at the child
     * clone and the flush persisted it.
     */
    public function testAnExistingRootTranslationIsNotRepointedAtTheChildClone(): void
    {
        [$root, $child] = $this->persistTree();

        $existingRootDe = new Node('Wurzel');
        $existingRootDe->setTuuid($root->getTuuid())->setLocale('de_DE');
        $this->entityManager()->persist($existingRootDe);
        $this->entityManager()->flush();
        $existingRootDeId = $existingRootDe->getId();
        self::assertNotNull($existingRootDeId);

        $childDe = $this->translator()->translateAndPersist($child, 'de_DE');
        $this->entityManager()->flush();
        self::assertInstanceOf(Node::class, $childDe);
        self::assertSame($existingRootDe, $childDe->getParent(), 'the existing de_DE root row is reused');

        $this->entityManager()->clear();

        $reloaded = new LocaleVariantFinder($this->entityManager())
            ->withoutLocaleFilter(fn (): object|null => $this->entityManager()->find(Node::class, $existingRootDeId));
        self::assertInstanceOf(Node::class, $reloaded);
        self::assertNull($reloaded->getParent(), 'the persisted root translation must still have parent_id NULL');
    }

    // ------------------------------------------------------------------
    // The neighbouring shapes that must keep working
    // ------------------------------------------------------------------

    /** The SPEC § 4 root-reference shape: `ManyToOne(inversedBy: 'translations')` to the root. */
    public function testARootReferenceWithInversedBySurvivesTranslate(): void
    {
        $root = RowRoot::mint();
        $row  = new RootedRow($root);
        $row->setLocale('en_US')->setTitle('t');
        $this->entityManager()->persist($root);
        $this->entityManager()->persist($row);
        $this->entityManager()->flush();

        $de = $this->translator()->translate($row, 'de_DE');
        self::assertInstanceOf(RootedRow::class, $de);
        self::assertSame($root, $de->getRoot(), 'the root is reaffirmed to the identical instance');
        self::assertSame((string) $root->getTuuid(), (string) $de->getTuuid());
    }

    /** A self-referential bidirectional OneToOne chain: `$next` owns, `$previous` mirrors. */
    public function testASelfReferentialOneToOneChainTranslatesWithoutPointingAtItself(): void
    {
        $first  = new Link('first');
        $second = new Link('second');
        $first->setLocale('en_US');
        $second->setLocale('en_US');
        $first->setNext($second);
        $this->entityManager()->persist($first);
        $this->entityManager()->persist($second);
        $this->entityManager()->flush();

        $firstDe = $this->translator()->translate($first, 'de_DE');
        self::assertInstanceOf(Link::class, $firstDe);

        $secondDe = $firstDe->getNext();
        self::assertInstanceOf(Link::class, $secondDe);
        self::assertNotSame($second, $secondDe);
        self::assertNotSame($firstDe, $secondDe, 'a clone never points at itself');
        self::assertSame('de_DE', $secondDe->getLocale());
        self::assertSame($firstDe, $secondDe->getPrevious(), 'the inverse side points back at the clone of the owner');
        self::assertNull($firstDe->getPrevious());
    }

    /**
     * @return array{Node, Node} root and child, both en_US, flushed
     */
    private function persistTree(): array
    {
        $root  = new Node('root');
        $child = new Node('child');
        $root->setLocale('en_US');
        $child->setLocale('en_US');
        $root->addChild($child);
        $this->entityManager()->persist($root);
        $this->entityManager()->persist($child);
        $this->entityManager()->flush();

        return [$root, $child];
    }
}
