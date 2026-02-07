<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\Model;

use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\Doctrine\Type\TuuidType;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class TranslatableTraitTest extends IntegrationTestCase
{
    /**
     * Ensure the custom Tuuid type is registered with Doctrine.
     *
     * @throws TypesException
     */
    public function testTuuidTypeIsRegistered(): void
    {
        self::assertTrue(Type::hasType(TuuidType::NAME), 'Tuuid Doctrine type is not registered.');
        $type = Type::getType(TuuidType::NAME);
        self::assertInstanceOf(TuuidType::class, $type, 'Registered Tuuid type is not an instance of TuuidType.');
    }

    /**
     * Check the column mapping of the "tuuid" property.
     */
    public function testTuuidColumnMapping(): void
    {
        $reflection = new \ReflectionClass(Scalar::class);
        $property   = $reflection->getProperty('tuuid');
        $attributes = $property->getAttributes(Column::class);

        self::assertNotEmpty($attributes, 'No #[ORM\Column] attribute found on "tuuid".');

        $columnAttr = $attributes[0]->newInstance();
        self::assertSame(Column::class, $attributes[0]->getName());

        self::assertSame(TuuidType::NAME, $columnAttr->type, 'The "tuuid" column must use type="tuuid".');
        self::assertTrue($columnAttr->nullable, 'The "tuuid" column must be nullable.');
    }

    /**
     * Ensure that Tuuid is auto-generated before persist.
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testPrePersistEvent(): void
    {
        $entity = new Scalar();

        // Lazy generation on first getTuuid() call
        $tuuid = $entity->getTuuid();
        self::assertNotEmpty($tuuid->__toString());

        $entity->setTitle('Test Entity');
        $this->entityManager()->persist($entity);
        $this->entityManager()->flush();

        self::assertNotEmpty($entity->getTuuid()->getValue());
        self::assertTrue(Uuid::isValid($entity->getTuuid()->__toString()));
    }

    /**
     * Invalid Tuuid value should throw an exception.
     */
    public function testSetInvalidTuuidThrowsException(): void
    {
        $invalidTuuid = 'not-a-valid-uuid';

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage(sprintf('Invalid Tuuid value: "%s"', $invalidTuuid));

        new Tuuid($invalidTuuid);
    }

    /**
     * Test getter/setter for locale property.
     */
    public function testLocaleMethods(): void
    {
        $entity = new Scalar();

        $entity->setLocale('de_DE');
        self::assertSame('de_DE', $entity->getLocale());

        $entity->setLocale(null);
        self::assertNull($entity->getLocale());
    }

    /**
     * Ensure getTuuid() auto-generates a Tuuid instance if null.
     */
    public function testGetTuuidAutoGenerates(): void
    {
        $entity     = new Scalar();
        $reflection = new \ReflectionClass($entity);
        $property   = $reflection->getProperty('tuuid');
        $property->setValue($entity, null);

        self::assertNull($property->getValue($entity));

        $generated = $entity->getTuuid();
        self::assertNotEmpty($generated->getValue());
        self::assertTrue(Uuid::isValid($generated->__toString()));

        // Ensure lazy initialization persists value internally
        self::assertSame($generated, $property->getValue($entity));
    }

    /**
     * Test translation array methods.
     */
    public function testTranslationMethods(): void
    {
        $entity = new Scalar();

        $translations = ['de_DE' => ['title' => 'Titel'], 'en_US' => ['title' => 'Title']];
        $entity->setTranslations($translations);
        self::assertSame($translations, $entity->getTranslations());

        $entity->setTranslation('fr', ['title' => 'Titre']);
        self::assertSame(['title' => 'Titre'], $entity->getTranslation('fr'));

        self::assertNull($entity->getTranslation('es'));
    }

    /**
     * Test setting Tuuid and immutability.
     */
    public function testTuuidMethods(): void
    {
        $entity = new Scalar();
        $tuuid  = Tuuid::generate();

        // Setting Tuuid for the first time works
        $entity->setTuuid($tuuid);
        self::assertTrue($entity->getTuuid()->equals($tuuid));

        // Reassigning Tuuid must throw a LogicException
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Tuuid is immutable and cannot be reassigned.');
        $entity->setTuuid(Tuuid::generate());
    }

    /**
     * Test generateTuuid() does not overwrite existing Tuuid.
     */
    public function testGenerateTuuid(): void
    {
        $entity = new Scalar();

        self::assertTrue(Uuid::isValid($entity->getTuuid()->__toString()));

        $existing = $entity->getTuuid();
        $entity->generateTuuid();
        self::assertSame($existing, $entity->getTuuid());
    }

    /**
     * Ensure Tuuid immutability is enforced.
     */
    public function testTuuidCannotBeReassigned(): void
    {
        $entity = new Scalar();
        $first  = Tuuid::generate();
        $entity->setTuuid($first);

        self::expectException(\LogicException::class);
        $entity->setTuuid(Tuuid::generate());
    }
}
