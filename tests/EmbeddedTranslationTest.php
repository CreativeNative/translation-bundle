<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\Address;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\Translatable;

/**
 * Tests for embedded entities.
 */
final class EmbeddedTranslationTest extends IntegrationTestCase
{
    public const string TARGET_LOCALE = 'en';

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanTranslateEmbeddedEntity(): void
    {
        $address = new Address()
            ->setStreet('13 place Sophie Trébuchet')
            ->setCity('Nantes')
            ->setPostalCode('44000')
            ->setCountry('France')
        ;
        $entity = new Translatable()
            ->setLocale('en')
            ->setAddress($address);

        $this->entityManager->persist($entity);

        $trans = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->persist($trans);

        $this->entityManager->flush();

        self::assertEquals($entity->getAddress(), $trans->getAddress());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testItCanEmptyEmbeddedEntity(): void
    {
        $address = new Address()
            ->setStreet('13 place Sophie Trébuchet')
            ->setCity('Nantes')
            ->setPostalCode('44000')
            ->setCountry('France')
        ;
        $entity = new Translatable()
            ->setLocale('en')
            ->setEmptyAddress($address);

        $this->entityManager->persist($entity);

        $trans = $this->translator->translate($entity, self::TARGET_LOCALE);
        assert($trans instanceof Translatable);

        $this->entityManager->persist($trans);

        $this->entityManager->flush();

        self::assertEquals($entity->getEmptyAddress(), $address);
        self::assertEmpty($trans->getEmptyAddress());
    }
}
