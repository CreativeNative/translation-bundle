<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\Doctrine\Type\TuuidType;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class TuuidIntegrationTest extends IntegrationTestCase
{
    private EntityManagerInterface $em;

    public function setUp(): void
    {
        parent::setUp();

        $this->em = $this->entityManager();

        // Ensure TuuidType is registered (again, safe)
        if (!Type::hasType(TuuidType::NAME)) {
            Type::addType(TuuidType::NAME, TuuidType::class);
        }

        $metadata   = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->em);

        // Drop & create schema for a clean start
        try {
            $schemaTool->dropSchema($metadata);
        } catch (\Exception) {
        }
        $schemaTool->createSchema($metadata);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testTuuidAutoGeneration(): void
    {
        $entity = new Scalar();
        $entity->setTitle('Test Auto Tuuid');

        $this->em->persist($entity);
        $this->em->flush();

        $tuuid = $entity->getTuuid();
        self::assertNotEmpty($tuuid->getValue());
        self::assertTrue(Uuid::isValid($tuuid->__toString()));
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testTuuidPersistenceAndFetch(): void
    {
        $entity = new Scalar();
        $entity->setTitle('Persisted Tuuid');
        $generatedTuuid = Tuuid::generate();
        $entity->setTuuid($generatedTuuid);

        $this->em->persist($entity);
        $this->em->flush();
        $this->em->clear();

        $fetched = $this->em->getRepository(Scalar::class)->find($entity->getId());
        self::assertNotNull($fetched, 'Entity should be found after persist+flush');
        $fetchedTuuid = $fetched->getTuuid();
        self::assertSame($generatedTuuid->getValue(), $fetchedTuuid->getValue());
        self::assertTrue($fetchedTuuid->equals($generatedTuuid));
    }

    public function testTuuidCannotBeReassigned(): void
    {
        $entity = new Scalar();
        $tuuid  = Tuuid::generate();
        $entity->setTuuid($tuuid);

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Tuuid is immutable and cannot be reassigned.');

        $entity->setTuuid(Tuuid::generate());
    }

    /**
     * @throws ConversionException
     * @throws Exception
     * @throws TypesException
     */
    public function testTuuidTypeConversion(): void
    {
        $type = Type::getType(TuuidType::NAME);

        $tuuid   = Tuuid::generate();
        $dbValue = $type->convertToDatabaseValue($tuuid, $this->em->getConnection()->getDatabasePlatform());

        self::assertSame($tuuid->getValue(), $dbValue);

        $phpValue = $type->convertToPHPValue($dbValue, $this->em->getConnection()->getDatabasePlatform());
        self::assertInstanceOf(Tuuid::class, $phpValue);
        self::assertTrue($phpValue->equals($tuuid));
    }

    /**
     * @throws ConversionException
     * @throws Exception
     * @throws TypesException
     */
    public function testTuuidNullConversionGeneratesNew(): void
    {
        $type = Type::getType(TuuidType::NAME);

        $phpValue = $type->convertToPHPValue(null, $this->em->getConnection()->getDatabasePlatform());
        self::assertInstanceOf(Tuuid::class, $phpValue);
        self::assertTrue(Uuid::isValid($phpValue->__toString()));

        $dbValue = $type->convertToDatabaseValue(null, $this->em->getConnection()->getDatabasePlatform());
        self::assertIsString($dbValue);
        self::assertTrue(Uuid::isValid($dbValue));
    }

    /**
     * @throws Exception
     * @throws TypesException
     */
    public function testTuuidInvalidConversionThrows(): void
    {
        $type = Type::getType(TuuidType::NAME);

        self::expectException(ConversionException::class);
        $type->convertToPHPValue('invalid', $this->em->getConnection()->getDatabasePlatform());
    }
}
