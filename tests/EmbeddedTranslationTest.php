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

        // Embedded is cloned (different instance)
        self::assertNotSame(
            $entity->getAddress(),
            $translated->getAddress(),
        );

        // Per-property resolution: unattributed properties reset to class defaults (null)
        $translatedAddress = $translated->getAddress();
        self::assertNotNull($translatedAddress);
        self::assertInstanceOf(Address::class, $translatedAddress);
        self::assertNull($translatedAddress->getStreet());
        self::assertNull($translatedAddress->getPostalCode());
        self::assertNull($translatedAddress->getCity());
        self::assertNull($translatedAddress->getCountry());
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
            $translated->getSharedAddress(),
        );

        // Shared property also identical
        $entitySharedAddress     = $entity->getSharedAddress();
        $translatedSharedAddress = $translated->getSharedAddress();
        self::assertNotNull($entitySharedAddress);
        self::assertNotNull($translatedSharedAddress);
        self::assertSame(
            $entitySharedAddress->getStreet(),
            $translatedSharedAddress->getStreet(),
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
            $translated->getEmptyAddress(),
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
        $entityAddress = $entity->getAddress();
        self::assertInstanceOf(AddressWithEmptyAndSharedProperty::class, $entityAddress);
        self::assertSame('Empty Street', $entityAddress->getStreet());

        self::assertSame('no Setter', $entityAddress->getNoSetter());
        $translatedAddress2 = $translated->getAddress();
        self::assertNotNull($translatedAddress2);
        self::assertInstanceOf(AddressWithEmptyAndSharedProperty::class, $translatedAddress2);
        self::assertNull($translatedAddress2->getNoSetter());

        // Translated entity should NOT be NULL
        self::assertNotNull($translated->getAddress());

        // Property marked #[EmptyOnTranslate] is null in translated entity
        self::assertNull($translated->getAddress()->getStreet());

        $entityAddr = $entity->getAddress();
        self::assertInstanceOf(AddressWithEmptyAndSharedProperty::class, $entityAddr);
        $translatedAddr = $translated->getAddress();
        self::assertInstanceOf(AddressWithEmptyAndSharedProperty::class, $translatedAddr);

        self::assertNotSame(
            $entityAddr->getStreet(),
            $translatedAddr->getStreet(),
        );

        // Per-property resolution: unattributed properties reset to class defaults (null)
        self::assertNull($translatedAddr->getPostalCode());
        self::assertNull($translatedAddr->getCity());

        // Property marked #[SharedAmongstTranslations] retains original value
        self::assertSame(
            $entityAddr->getCountry(),
            $translatedAddr->getCountry(),
        );

        // Cloned embeddable is a new object
        self::assertNotSame(
            $entity->getAddress(),
            $translated->getAddress(),
        );
    }

    /**
     * Helper to create a Translatable entity with optional attributes.
     *
     * @throws ORMException
     */
    private function createTranslatableEntity(
        Address|AddressWithEmptyAndSharedProperty|null $address = null,
        bool $shared = false,
        bool $emptyOnTranslate = false,
    ): Translatable {
        $entity = new Translatable();
        $entity->setLocale('en_US');

        $address ??= new Address();

        // Narrow the address type for methods that only accept Address
        $narrowedAddress = $address instanceof Address ? $address : null;
        if ($shared) {
            $entity->setSharedAddress($narrowedAddress);
        } elseif ($emptyOnTranslate) {
            $entity->setEmptyAddress($narrowedAddress);
        } else {
            $entity->setAddress($address);
        }

        $this->entityManager()->persist($entity);

        return $entity;
    }

    /**
     * Helper to translate and persist entity.
     *
     * @throws ORMException
     */
    private function translateAndPersist(Translatable $entity): Translatable
    {
        $translated = $this->translator()->translate($entity, self::TARGET_LOCALE);
        self::assertInstanceOf(Translatable::class, $translated);

        $this->entityManager()->persist($translated);
        $this->entityManager()->flush();

        return $translated;
    }
}
