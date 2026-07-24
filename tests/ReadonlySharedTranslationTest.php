<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Tmi\TranslationBundle\Fixtures\Entity\ReadonlyShared\ReadonlyShared;

/**
 * A readonly property combined with #[SharedAmongstTranslations] is an allowed shape — only
 * readonly + #[EmptyOnTranslate] is rejected by validation. Translating such an entity used
 * to abort with "Error: Cannot modify readonly property" while writing the identical value
 * back onto the clone, which made the combination unusable in practice.
 */
final class ReadonlySharedTranslationTest extends IntegrationTestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testEntityWithReadonlySharedPropertyCanBePersisted(): void
    {
        $entity = new ReadonlyShared('SKU-1')->setLocale('en_US')->setTitle('English')->setNote('shared');

        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();

        self::assertNotNull($entity->getId());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testReadonlySharedValueSurvivesTranslation(): void
    {
        $entity = new ReadonlyShared('SKU-1')->setLocale('en_US')->setTitle('English')->setNote('shared');

        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();

        $translation = $this->translator()->translate($entity, self::TARGET_LOCALE);
        self::assertInstanceOf(ReadonlyShared::class, $translation);

        $this->entityManager()->persist($translation);
        $this->entityManager()->flush();

        self::assertIsTranslation($entity, $translation, self::TARGET_LOCALE);
        self::assertSame('SKU-1', $translation->getSku());
        self::assertSame('shared', $translation->getNote());
    }
}
