<?php

namespace TMI\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneUnidirectional;

/**
 * @author Arthur Guigand <aguigand@tmi.fr>
 */
final class TranslatableOneToOneUnidirectionalTest extends TestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateSimpleValue(): void
    {
        $associatedEntity = new Scalar()
            ->setLocale('en')
            ->setTitle('simple');

        $entity = new TranslatableOneToOneUnidirectional()
                ->setLocale('en')
                ->setSimple($associatedEntity);

        $this->entityManager->persist($entity);

        /** @var TranslatableOneToOneUnidirectional $translation */
        $translation = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->flush();
        $this->assertNotEquals($associatedEntity, $translation->getSimple());
        $this->assertEquals(self::TARGET_LOCALE, $translation->getSimple()->getLocale());
        $this->assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function testItCanShareTranslatableEntityValueAmongstTranslations(): void
    {
        $associatedEntity1 = new Scalar()
            ->setLocale('en')
            ->setTitle('shared');

        $associatedEntity2 = new Scalar()
            ->setLocale('en')
            ->setTitle('shared');

        $entity = new TranslatableOneToOneUnidirectional()
            ->setLocale('en')
            ->setShared($associatedEntity1);

        $this->entityManager->persist($entity);

        /** @var TranslatableOneToOneUnidirectional $translation */
        $translation = $this->translator->translate($entity, self::TARGET_LOCALE);
        $translation->setShared($associatedEntity2);

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        $this->assertEquals('shared', $translation->getShared()->getTitle());
        $this->assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanEmptyTranslatableEntityValue(): void
    {
        $associatedEntity = new Scalar()
            ->setLocale('en')
            ->setTitle('empty');

        $entity = new TranslatableOneToOneUnidirectional()
                ->setLocale('en')
                ->setEmpty($associatedEntity);

        $this->entityManager->persist($entity);

        /** @var TranslatableOneToOneUnidirectional $translation */
        $translation = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        $this->assertEquals(null, $translation->getEmpty());
        $this->assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
    }
}
