<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\Repository;

use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\ScalarRepository;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class TranslatableRepositoryTraitTest extends IntegrationTestCase
{
    private ScalarRepository $repository;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->entityManager()->getRepository(Scalar::class);
    }

    public function testFindAllLocaleVariantsSingleLocale(): void
    {
        $entity = new Scalar();
        $entity->setTitle('Only English');
        $entity->setLocale('en_US');

        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();

        $tuuid    = $entity->getTuuid();
        $variants = $this->repository->findAllLocaleVariants($tuuid);

        self::assertCount(1, $variants);
        self::assertArrayHasKey('en_US', $variants);
        $enVariant = $variants['en_US'];
        self::assertInstanceOf(Scalar::class, $enVariant);
        self::assertSame($entity->getId(), $enVariant->getId());
    }

    public function testFindAllLocaleVariantsMultipleLocales(): void
    {
        $tuuid = Tuuid::generate();

        $en = new Scalar();
        $en->setTuuid($tuuid);
        $en->setTitle('English');
        $en->setLocale('en_US');

        $de = new Scalar();
        $de->setTuuid($tuuid);
        $de->setTitle('German');
        $de->setLocale('de_DE');

        $this->entityManager()->persist($en);
        $this->entityManager()->persist($de);
        $this->entityManager()->flush();

        $variants = $this->repository->findAllLocaleVariants($tuuid);

        self::assertCount(2, $variants);
        self::assertArrayHasKey('en_US', $variants);
        self::assertArrayHasKey('de_DE', $variants);
        $enVariant = $variants['en_US'];
        $deVariant = $variants['de_DE'];
        self::assertInstanceOf(Scalar::class, $enVariant);
        self::assertInstanceOf(Scalar::class, $deVariant);
        self::assertSame('English', $enVariant->getTitle());
        self::assertSame('German', $deVariant->getTitle());
    }

    public function testFindAllLocaleVariantsNonexistentTuuid(): void
    {
        $variants = $this->repository->findAllLocaleVariants(Tuuid::generate());

        self::assertSame([], $variants);
    }

    public function testFindAllLocaleVariantsBatchEmptyInput(): void
    {
        $result = $this->repository->findAllLocaleVariantsBatch([]);

        self::assertSame([], $result);
    }

    public function testFindAllLocaleVariantsBatchMultipleTuuids(): void
    {
        $tuuid1 = Tuuid::generate();
        $tuuid2 = Tuuid::generate();

        $en1 = new Scalar();
        $en1->setTuuid($tuuid1);
        $en1->setTitle('Entity 1 EN');
        $en1->setLocale('en_US');

        $de1 = new Scalar();
        $de1->setTuuid($tuuid1);
        $de1->setTitle('Entity 1 DE');
        $de1->setLocale('de_DE');

        $en2 = new Scalar();
        $en2->setTuuid($tuuid2);
        $en2->setTitle('Entity 2 EN');
        $en2->setLocale('en_US');

        $this->entityManager()->persist($en1);
        $this->entityManager()->persist($de1);
        $this->entityManager()->persist($en2);
        $this->entityManager()->flush();

        $result = $this->repository->findAllLocaleVariantsBatch([$tuuid1, $tuuid2]);

        self::assertCount(2, $result);

        self::assertArrayHasKey((string) $tuuid1, $result);
        self::assertCount(2, $result[(string) $tuuid1]);
        self::assertArrayHasKey('en_US', $result[(string) $tuuid1]);
        self::assertArrayHasKey('de_DE', $result[(string) $tuuid1]);

        self::assertArrayHasKey((string) $tuuid2, $result);
        self::assertCount(1, $result[(string) $tuuid2]);
        self::assertArrayHasKey('en_US', $result[(string) $tuuid2]);
    }
}
