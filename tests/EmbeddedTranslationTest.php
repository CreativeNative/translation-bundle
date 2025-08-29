<?php

namespace TMI\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use TMI\TranslationBundle\Fixtures\Entity\Embedded\Address;
use TMI\TranslationBundle\Fixtures\Entity\Embedded\Translatable;

/**
 * Tests for embedded entities.
 */
final class EmbeddedTranslationTest extends TestCase
{
    const string TARGET_LOCALE = 'en';

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
        $entity  = new Translatable()
            ->setLocale('en')
            ->setAddress($address);

        $this->entityManager->persist($entity);

        $trans = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->persist($trans);

        $this->entityManager->flush();

        $this->assertEquals($entity->getAddress(), $trans->getAddress());
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
        $entity  = new Translatable()
            ->setLocale('en')
            ->setEmptyAddress($address);

        $this->entityManager->persist($entity);

        /** @var Translatable $trans */
        $trans = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->persist($trans);

        $this->entityManager->flush();

        $this->assertEquals($entity->getEmptyAddress(), $address);
        $this->assertEmpty($trans->getEmptyAddress());
    }
}
