<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalParent;

final class TranslatableOneToOneBidirectionalTest extends IntegrationTestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateSimpleValue(): void
    {
        $child  = new TranslatableOneToOneBidirectionalChild()->setLocale('en_US');
        $parent = new TranslatableOneToOneBidirectionalParent()->setLocale('en_US');

        $parent->setSimpleChild($child);
        $child->setSimpleParent($parent);

        $this->entityManager()->persist($parent);

        $parentTranslation = $this->translator()->translate($parent, self::TARGET_LOCALE);

        $this->entityManager()->persist($parentTranslation);
        $this->entityManager()->flush();

        self::assertIsTranslation($parent, $parentTranslation, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableOneToOneBidirectionalParent::class, $parentTranslation);
        $simpleChild = $parentTranslation->getSimpleChild();
        self::assertNotNull($simpleChild);
        self::assertEquals(self::TARGET_LOCALE, $simpleChild->getLocale());
    }

    public function testItCannotShareTranslatableEntityValueAmongstTranslations(): void
    {
        self::expectException(\ErrorException::class);

        $child  = new TranslatableOneToOneBidirectionalChild()->setLocale('en_US');
        $parent = new TranslatableOneToOneBidirectionalParent()->setLocale('en_US');

        $parent->setSharedChild($child);
        $child->setSharedParent($parent);

        $this->translator()->translate($parent, self::TARGET_LOCALE);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanEmptyTranslatableEntityValue(): void
    {
        $child = new TranslatableOneToOneBidirectionalChild()
            ->setLocale('en_US');
        $parent = new TranslatableOneToOneBidirectionalParent()
            ->setLocale('en_US');

        $parent->setEmptyChild($child);
        $child->setEmptyParent($parent);

        $this->entityManager()->persist($parent);
        $this->entityManager()->persist($child);

        $parentTranslation = $this->translator()->translate($parent, self::TARGET_LOCALE);

        $this->entityManager()->persist($parentTranslation);
        $this->entityManager()->flush();

        self::assertIsTranslation($parent, $parentTranslation, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableOneToOneBidirectionalParent::class, $parentTranslation);

        self::assertNull($parentTranslation->getEmptyChild());
    }

    /**
     * The child clone must run the full entity pipeline, not a shallow `clone $data`:
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
        $child = new TranslatableOneToOneBidirectionalChild()
            ->setLocale('en_US')
            ->setTitle('Widget')
            ->setShared('shared-value')
            ->setEmpty('secret');
        $parent = new TranslatableOneToOneBidirectionalParent()->setLocale('en_US');

        $parent->setSimpleChild($child);
        $child->setSimpleParent($parent);

        $this->entityManager()->persist($parent);
        $this->entityManager()->persist($child);
        $this->entityManager()->flush();

        self::assertNotNull($child->getId(), 'Source child must be persisted before translating');

        $parentTranslation = $this->translator()->translate($parent, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableOneToOneBidirectionalParent::class, $parentTranslation);

        $translatedChild = $parentTranslation->getSimpleChild();
        self::assertInstanceOf(TranslatableOneToOneBidirectionalChild::class, $translatedChild);
        self::assertNotSame($child, $translatedChild);
        self::assertNull($translatedChild->getId(), 'Generated id must be reset, not copied verbatim from the source row');
        self::assertSame('Widget', $translatedChild->getTitle(), 'Translatable field follows copy_source: true');
        self::assertSame('shared-value', $translatedChild->getShared());
        self::assertNull($translatedChild->getEmpty(), 'EmptyOnTranslate must clear the field -- a shallow clone would have kept it');
        self::assertSame($parentTranslation, $translatedChild->getSimpleParent());
        self::assertSame(self::TARGET_LOCALE, $translatedChild->getLocale());

        $this->entityManager()->persist($parentTranslation);
        $this->entityManager()->flush();

        self::assertIsTranslation($child, $translatedChild, self::TARGET_LOCALE);
    }
}
