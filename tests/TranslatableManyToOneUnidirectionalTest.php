<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\NonTranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOneUnidirectional;

/**
 * Unidirectional ManyToOne (no inversedBy): none of the five dedicated association
 * handlers' supports() match this shape, so it falls through to TranslatableEntityHandler
 * as the catch-all.
 */
final class TranslatableManyToOneUnidirectionalTest extends IntegrationTestCase
{
    public function testItCannotShareTranslatableEntityValueAmongstTranslations(): void
    {
        $associated = new Scalar()
            ->setLocale('en_US')
            ->setTitle('shared');

        $entity = new TranslatableManyToOneUnidirectional()
            ->setLocale('en_US')
            ->setSharedToTranslatable($associated);

        $this->entityManager()->persist($entity);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessageMatches('/sharedToTranslatable/');

        $this->translator()->translate($entity, self::TARGET_LOCALE);
    }

    /**
     * A #[SharedAmongstTranslations] association whose target is NOT translatable is
     * unaffected by the rejection above -- it never becomes an EntityTranslationContext
     * (the target has no locale of its own to translate into), so it never reaches
     * TranslatableEntityHandler at all. It is resolved by DoctrineObjectHandler's own
     * isShared() branch instead, which returns the exact same instance.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function testItCanShareNonTranslatableEntityValueAmongstTranslations(): void
    {
        $associated = new NonTranslatableManyToOneBidirectionalChild()
            ->setTitle('shared');

        $entity = new TranslatableManyToOneUnidirectional()
            ->setLocale('en_US')
            ->setSharedToNonTranslatable($associated);

        $this->entityManager()->persist($entity);

        $translation = $this->translator()->translate($entity, self::TARGET_LOCALE);
        self::assertInstanceOf(TranslatableManyToOneUnidirectional::class, $translation);

        $this->entityManager()->persist($translation);
        $this->entityManager()->flush();

        self::assertSame($associated, $translation->getSharedToNonTranslatable());
        self::assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
    }
}
