<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToManyBidirectionalParent;

final class TranslatableManyToOneBidirectionalTest extends IntegrationTestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateSimpleValue(): void
    {
        $parent = new TranslatableOneToManyBidirectionalParent()
            ->setLocale('de_DE');
        $this->entityManager()->persist($parent);

        $child = new TranslatableManyToOneBidirectionalChild()
            ->setLocale('de_DE');
        $child->setParentSimple($parent);
        $parent->getSimpleChildren()->add($child);

        $this->entityManager()->persist($child);
        $this->entityManager()->flush();

        $translation = $this->translator()->translate($parent, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $translation);

        $this->entityManager()->persist($translation);
        $this->entityManager()->flush();

        $translatedChild = $translation->getSimpleChildren()->first();
        self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $translatedChild);
        if ($translatedChild->getLocale() !== $child->getLocale()) {
            self::assertNotSame($child, $translatedChild); // Only if translation occurred
        } else {
            self::assertSame($child, $translatedChild); // Original returned if no translation
        }

        self::assertEquals(self::TARGET_LOCALE, $translatedChild->getLocale());
        self::assertIsTranslation($parent, $translation, self::TARGET_LOCALE);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItMustAssociateExistingTranslation(): void
    {
        // --- Step 1: Create and persist the original child ---
        $child = new TranslatableManyToOneBidirectionalChild();
        $child->setParentSimple(null);
        $child->setLocale('de_DE');

        $this->entityManager()->persist($child);
        $this->entityManager()->flush();

        self::assertNotEmpty($child->getTuuid()->getValue());
        self::assertTrue(Uuid::isValid($child->getTuuid()->getValue()));

        // --- Step 2: Create and persist the parent ---
        $parent = new TranslatableOneToManyBidirectionalParent();
        $parent->setLocale('de_DE');
        $parent->getSimpleChildren()->add($child);
        $child->setParentSimple($parent);

        $this->entityManager()->persist($parent);
        $this->entityManager()->flush();

        self::assertNotEmpty($parent->getTuuid()->getValue());
        self::assertTrue(Uuid::isValid($parent->getTuuid()->getValue()));

        $translatedChild = $this->translator()->translate($child, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $translatedChild);
        $parent->getSimpleChildren()->add($translatedChild);
        $translatedChild->setParentSimple($parent);

        // --- Step 3: Translate the parent (Child will be translated automatically) ---
        $translation = $this->translator()->translate($parent, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $translation);

        $this->entityManager()->persist($translation);
        $this->entityManager()->flush();

        // --- Step 4: Assertions ---
        $translatedChildFromParent = $translation->getSimpleChildren()->first();
        self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $translatedChildFromParent);

        // Adjust assertion for object identity
        if ($translatedChildFromParent->getLocale() !== $child->getLocale()) {
            self::assertNotSame($child, $translatedChildFromParent);
        } else {
            self::assertSame($child, $translatedChildFromParent);
        }

        self::assertSame(self::TARGET_LOCALE, $translatedChildFromParent->getLocale());
        self::assertIsTranslation($parent, $translation, self::TARGET_LOCALE);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function testItCanShareTranslatableEntityValueAmongstTranslations(): void
    {
        $child = new TranslatableManyToOneBidirectionalChild();
        $child->setLocale('de_DE');

        $this->entityManager()->persist($child);
        $this->entityManager()->flush();

        $translatedChild = $this->translator()->translate($child, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $translatedChild);

        $this->entityManager()->persist($translatedChild);
        $this->entityManager()->flush();

        $parent = new TranslatableOneToManyBidirectionalParent();
        $parent->setLocale('de_DE');

        $parent->getSimpleChildren()->add($translatedChild);
        $translatedChild->setParentSimple($parent);

        $this->entityManager()->persist($parent);

        $translation = $this->translator()->translate($parent, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $translation);

        $this->entityManager()->persist($translation);
        $this->entityManager()->flush();

        self::assertEquals($translatedChild, $translation->getSimpleChildren()->first());
        self::assertIsTranslation($parent, $translation, self::TARGET_LOCALE);
    }

    /**
     * The child clone must run the full entity pipeline, not a shallow `clone $entity`:
     * its own generated id is reset (a persisted source row's id would otherwise be
     * copied verbatim onto the clone), its shared field is copied, its EmptyOnTranslate
     * field is cleared, and its own translatable field follows copy_source (true by
     * default in the test kernel) -- on top of the back-reference to the translated
     * parent, which already worked before this fix.
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testChildCloneRunsTheFullEntityPipeline(): void
    {
        $parent = new TranslatableOneToManyBidirectionalParent()->setLocale('en_US');
        $child  = new TranslatableManyToOneBidirectionalChild()
            ->setLocale('en_US')
            ->setTitle('Widget')
            ->setShared('shared-value')
            ->setEmpty('secret');
        $child->setParentSimple($parent);
        $parent->getSimpleChildren()->add($child);

        $this->entityManager()->persist($parent);
        $this->entityManager()->persist($child);
        $this->entityManager()->flush();

        self::assertNotNull($child->getId(), 'Source child must be persisted before translating');

        $translation = $this->translator()->translate($parent, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $translation);

        $translatedChild = $translation->getSimpleChildren()->first();
        self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $translatedChild);
        self::assertNotSame($child, $translatedChild);
        self::assertNull($translatedChild->getId(), 'Generated id must be reset, not copied verbatim from the source row');
        self::assertSame('Widget', $translatedChild->getTitle(), 'Translatable field follows copy_source: true');
        self::assertSame('shared-value', $translatedChild->getShared());
        self::assertNull($translatedChild->getEmpty(), 'EmptyOnTranslate must clear the field -- a shallow clone would have kept it');
        self::assertSame($translation, $translatedChild->getParentSimple());
        self::assertSame(self::TARGET_LOCALE, $translatedChild->getLocale());

        $this->entityManager()->persist($translation);
        $this->entityManager()->flush();

        self::assertIsTranslation($child, $translatedChild, self::TARGET_LOCALE);
    }

    /**
     * The direct form (#15): a plain ManyToOne property declared on a *different*, owning
     * class than the entity it points at -- here, the child's own `parentSimple` field
     * reached by translating the child directly rather than through
     * BidirectionalOneToManyHandler. The old code looked up the property's name in the
     * *target's* own association mappings, never found it there, and silently returned the
     * untranslated source parent. The fix translates the target to the matching locale
     * (get-or-create) instead.
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testDirectFormTranslatesTheRelatedEntityInsteadOfReturningItUnchanged(): void
    {
        $parent = new TranslatableOneToManyBidirectionalParent()->setLocale('en_US');
        $this->entityManager()->persist($parent);

        // Deliberately not added to $parent->getSimpleChildren(): the owning side
        // (parentSimple, below) is what Doctrine persists from, and keeping the inverse
        // collection empty avoids also pipelining it here, which would recurse back into
        // this very child through BidirectionalOneToManyHandler -- a separate, pre-existing
        // interaction this test does not need to exercise.
        $child = new TranslatableManyToOneBidirectionalChild()->setLocale('en_US');
        $child->setParentSimple($parent);

        $this->entityManager()->persist($child);
        $this->entityManager()->flush();

        // Translate the child on its own -- property is null at this top level, so
        // TranslatableEntityHandler runs the pipeline over the child's own properties,
        // including 'parentSimple', declared on the child's class and pointing at a
        // *different* class (the direct form).
        $childTranslation = $this->translator()->translate($child, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableManyToOneBidirectionalChild::class, $childTranslation);

        $translatedParent = $childTranslation->getParentSimple();
        self::assertInstanceOf(TranslatableOneToManyBidirectionalParent::class, $translatedParent);
        self::assertNotSame($parent, $translatedParent, 'The direct form must translate the target instead of returning the untranslated source');
        self::assertSame(self::TARGET_LOCALE, $translatedParent->getLocale());
        self::assertEquals($parent->getTuuid(), $translatedParent->getTuuid());

        // parentSimple carries no cascade -- the direct form documents that the target
        // needs its own persist() (or a mapping-level cascade) to be saved.
        $this->entityManager()->persist($childTranslation);
        $this->entityManager()->persist($translatedParent);
        $this->entityManager()->flush();

        self::assertIsTranslation($parent, $translatedParent, self::TARGET_LOCALE);
    }
}
