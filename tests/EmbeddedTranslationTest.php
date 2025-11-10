<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Fixtures\Entity\Embedded\AddressWithEmptyAndSharedProperty;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\Address;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\Translatable;

/**
 * Integration tests for embedded entities translation.
 */
final class EmbeddedTranslationTest extends IntegrationTestCase
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testNormalEmbeddedEntityIsCloned(): void
    {
        $address = new Address()
            ->setStreet('Normal Street')       // normal
            ->setPostalCode('12345')       // normal
            ->setCity('Normal City')            // normal
            ->setCountry('Normal Country');   // normal

        $entity = $this->createTranslatableEntity(address: $address);

        $translated = $this->translateAndPersist($entity);

        // Normal fields are cloned
        self::assertNotSame(
            $entity->getAddress(),
            $translated->getAddress()
        );

        self::assertEquals(
            $entity->getAddress(),
            $translated->getAddress()
        );

        // Properties within Address should remain same
        self::assertSame(
            $entity->getAddress()->getStreet(),
            $translated->getAddress()->getStreet()
        );

        self::assertSame(
            $entity->getAddress()->getPostalCode(),
            $translated->getAddress()->getPostalCode()
        );

        self::assertSame(
            $entity->getAddress()->getCity(),
            $translated->getAddress()->getCity()
        );

        self::assertSame(
            $entity->getAddress()->getCountry(),
            $translated->getAddress()->getCountry()
        );
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testEmbeddedEntityWithTopLevelSharedAmongstTranslations(): void
    {
        $address = new Address()
            ->setStreet('Normal Street')       // normal
            ->setPostalCode('12345')       // normal
            ->setCity('Normal City')            // normal
            ->setCountry('Normal Country');   // normal

        $entity = $this->createTranslatableEntity(address: $address, shared: true);

        $translated = $this->translateAndPersist($entity);

        // Shared Address instance is identical
        self::assertSame(
            $entity->getSharedAddress(),
            $translated->getSharedAddress()
        );

        // Shared property also identical
        self::assertSame(
            $entity->getSharedAddress()->getStreet(),
            $translated->getSharedAddress()->getStreet()
        );
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testEmbeddedEntityWithTopLevelEmptyOnTranslate(): void
    {
        $address = new Address()
            ->setStreet('Normal Street')       // normal
            ->setPostalCode('12345')       // normal
            ->setCity('Normal City')            // normal
            ->setCountry('Normal Country');   // normal

        $entity = $this->createTranslatableEntity(address: $address, emptyOnTranslate: true);

        $translated = $this->translateAndPersist($entity);

        // Original entity should NOT be NULL
        self::assertNotNull($entity->getEmptyAddress());

        // Translated entity should be NULL
        self::assertNull($translated->getEmptyAddress());

        self::assertNotSame(
            $entity->getEmptyAddress(),
            $translated->getEmptyAddress()
        );
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testEmbeddedEntityWithEmptyOnTranslateProperty(): void
    {
        $address = new AddressWithEmptyAndSharedProperty()
            ->setStreet('Empty Street') // #[EmptyOnTranslate]
            ->setPostalCode('12345') // normal
            ->setCity('Normal City') // normal
            ->setCountry('Shared Country'); // #[SharedAmongstTranslations]

        $entity = $this->createTranslatableEntity(address: $address);

        $translated = $this->translateAndPersist($entity);

        // Original entity should NOT be NULL
        self::assertNotNull($entity->getAddress());

        self::assertSame('Empty Street', $entity->getAddress()->getStreet());

        self::assertSame('no Setter', $entity->getAddress()->getNoSetter());
        self::assertNull($translated->getAddress()->getNoSetter());

        // Translated entity should NOT be be NULL
        self::assertNotNull($translated->getAddress());

        // Property marked #[EmptyOnTranslate] is null in translated entity
        self::assertNull($translated->getAddress()->getStreet());

        self::assertNotSame(
            $entity->getAddress()->getStreet(),
            $translated->getAddress()->getStreet()
        );

        // Other properties remain unchanged
        self::assertSame(
            $entity->getAddress()->getPostalCode(),
            $translated->getAddress()->getPostalCode()
        );

        self::assertSame(
            $entity->getAddress()->getCity(),
            $translated->getAddress()->getCity()
        );

        self::assertSame(
            $entity->getAddress()->getCountry(),
            $translated->getAddress()->getCountry()
        );

        // Cloned embeddable is a new object
        self::assertNotSame(
            $entity->getAddress(),
            $translated->getAddress()
        );
    }

    /**
     * Helper to create a Translatable entity with optional attributes.
     * @throws ORMException
     */
    private function createTranslatableEntity(
        Address|AddressWithEmptyAndSharedProperty|null $address = null,
        bool                                           $shared = false,
        bool                                           $emptyOnTranslate = false
    ): Translatable {
        $entity = new Translatable();
        $entity->setLocale('en_US');

        $address ??= new Address();
        if ($shared) {
            $entity->setSharedAddress($address);
        } elseif ($emptyOnTranslate) {
            $entity->setEmptyAddress($address);
        } else {
            $entity->setAddress($address);
        }

        $this->entityManager->persist($entity);
        return $entity;
    }

    /**
     * Helper to translate and persist entity.
     *
     * @throws ORMException
     */
    private function translateAndPersist(Translatable $entity): Translatable
    {
        $translated = $this->translator->translate($entity, self::TARGET_LOCALE);
        assert($translated instanceof Translatable);

        $this->entityManager->persist($translated);
        $this->entityManager->flush();

        return $translated;
    }
}
