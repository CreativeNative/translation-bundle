<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\Repository;

use Tmi\TranslationBundle\Doctrine\Repository\TranslatableEntityRepository;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class TranslatableEntityRepositoryTest extends IntegrationTestCase
{
    public function testProvidesLocaleVariantHelpers(): void
    {
        /** @var TranslatableEntityRepository<Scalar> $repository */
        $repository = new TranslatableEntityRepository(
            $this->entityManager(),
            $this->entityManager()->getClassMetadata(Scalar::class),
        );

        // Empty result proves the trait method runs through the base class.
        self::assertSame([], $repository->findAllLocaleVariants(Tuuid::generate()));
    }

    public function testReturnsPersistedLocaleVariants(): void
    {
        $tuuid = Tuuid::generate();

        $en = new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('English');
        $de = new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('German');

        $this->entityManager()->persist($en);
        $this->entityManager()->persist($de);
        $this->entityManager()->flush();

        /** @var TranslatableEntityRepository<Scalar> $repository */
        $repository = new TranslatableEntityRepository(
            $this->entityManager(),
            $this->entityManager()->getClassMetadata(Scalar::class),
        );

        $variants = $repository->findAllLocaleVariants($tuuid);

        self::assertCount(2, $variants);
        self::assertArrayHasKey('en_US', $variants);
        self::assertArrayHasKey('de_DE', $variants);
    }
}
