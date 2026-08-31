<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\InheritedIdEntity;

/**
 * Regression: an entity whose generated id is declared PRIVATE on a mapped
 * superclass must not carry the source's id on a freshly translated variant.
 * persist() used to hide the bug by overwriting the id at flush time — any
 * consumer reading getId() before the flush saw the source's id.
 */
final class InheritedIdTranslationTest extends IntegrationTestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testFreshVariantDoesNotCarryTheSourceId(): void
    {
        $entity = new InheritedIdEntity();
        $entity->setLocale('en_US');
        $entity->setTitle('English title');

        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();

        self::assertNotNull($entity->getId(), 'Source must have a generated id after flush');

        $translation = $this->translator()->translate($entity, self::TARGET_LOCALE);
        self::assertInstanceOf(InheritedIdEntity::class, $translation);

        self::assertNull(
            $translation->getId(),
            'A fresh variant must report null before it is flushed — not the id of its source',
        );

        $this->entityManager()->persist($translation);
        $this->entityManager()->flush();

        self::assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
        self::assertNotNull($translation->getId());
        self::assertNotSame($entity->getId(), $translation->getId(), 'Variant must get its own generated id');
        self::assertSame('English title', $translation->getTitle());
    }

    /**
     * Private parent-class columns must run through the translation pipeline —
     * a plain clone would copy #[EmptyOnTranslate] values verbatim.
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testInheritedPrivatePropertiesRunThroughTheTranslationPipeline(): void
    {
        $entity = new InheritedIdEntity();
        $entity->setLocale('en_US');
        $entity->setTitle('English title');
        $entity->setNotes('English notes');
        $entity->setSharedCode('CODE-1');
        $entity->setEphemeral('draft leftovers');

        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();

        $translation = $this->translator()->translate($entity, self::TARGET_LOCALE);
        self::assertInstanceOf(InheritedIdEntity::class, $translation);

        self::assertSame('English notes', $translation->getNotes(), 'Plain inherited column is copied to the variant');
        self::assertSame('CODE-1', $translation->getSharedCode(), 'Inherited #[SharedAmongstTranslations] column keeps the source value');
        self::assertNull($translation->getEphemeral(), 'Inherited #[EmptyOnTranslate] column must be emptied on the variant');

        $this->entityManager()->persist($translation);
        $this->entityManager()->flush();

        self::assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
    }
}
