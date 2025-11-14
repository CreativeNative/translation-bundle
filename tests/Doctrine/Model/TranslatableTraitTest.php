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
        $this->assertTrue(Type::hasType(TuuidType::NAME), 'Tuuid Doctrine type is not registered.');
        $type = Type::getType(TuuidType::NAME);
        $this->assertInstanceOf(TuuidType::class, $type, 'Registered Tuuid type is not an instance of TuuidType.');
    }

    /**
     * Check the column mapping of the "tuuid" property.
     */
    public function testTuuidColumnMapping(): void
    {
        $reflection = new \ReflectionClass(Scalar::class);
        $property   = $reflection->getProperty('tuuid');
        $attributes = $property->getAttributes(Column::class);

        $this->assertNotEmpty($attributes, 'No #[ORM\Column] attribute found on "tuuid".');

        $columnAttr = $attributes[0]->newInstance();
        $this->assertInstanceOf(Column::class, $columnAttr);

        $this->assertSame(TuuidType::NAME, $columnAttr->type, 'The "tuuid" column must use type="tuuid".');
        $this->assertTrue($columnAttr->nullable, 'The "tuuid" column must be nullable.');
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
        $this->assertNotNull($entity->getTuuid());

        $entity->setTitle('Test Entity');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->assertInstanceOf(Tuuid::class, $entity->getTuuid());
        $this->assertTrue(Uuid::isValid($entity->getTuuid()->__toString()));
    }

    /**
     * Invalid Tuuid value should throw an exception.
     */
    public function testSetInvalidTuuidThrowsException(): void
    {
        $invalidTuuid = 'not-a-valid-uuid';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Invalid Tuuid value: "%s"', $invalidTuuid));

        new Tuuid($invalidTuuid);
    }

    /**
     * Test getter/setter for locale property.
     */
    public function testLocaleMethods(): void
    {
        $entity = new Scalar();

        $entity->setLocale('de_DE');
        $this->assertSame('de_DE', $entity->getLocale());

        $entity->setLocale(null);
        $this->assertNull($entity->getLocale());
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

        $this->assertNull($property->getValue($entity));

        $generated = $entity->getTuuid();
        $this->assertInstanceOf(Tuuid::class, $generated);
        $this->assertTrue(Uuid::isValid($generated->__toString()));

        // Ensure lazy initialization persists value internally
        $this->assertSame($generated, $property->getValue($entity));
    }

    /**
     * Test translation array methods.
     */
    public function testTranslationMethods(): void
    {
        $entity = new Scalar();

        $translations = ['de_DE' => ['title' => 'Titel'], 'en_US' => ['title' => 'Title']];
        $entity->setTranslations($translations);
        $this->assertSame($translations, $entity->getTranslations());

        $entity->setTranslation('fr', ['title' => 'Titre']);
        $this->assertSame(['title' => 'Titre'], $entity->getTranslation('fr'));

        $this->assertNull($entity->getTranslation('es'));
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
        $this->assertTrue($entity->getTuuid()->equals($tuuid));

        // Reassigning Tuuid must throw a LogicException
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Tuuid is immutable and cannot be reassigned.');
        $entity->setTuuid(Tuuid::generate());
    }

    /**
     * Test generateTuuid() does not overwrite existing Tuuid.
     */
    public function testGenerateTuuid(): void
    {
        $entity = new Scalar();

        $this->assertInstanceOf(Tuuid::class, $entity->getTuuid());
        $this->assertTrue(Uuid::isValid($entity->getTuuid()->__toString()));

        $existing = $entity->getTuuid();
        $entity->generateTuuid();
        $this->assertSame($existing, $entity->getTuuid());
    }

    /**
     * Ensure Tuuid immutability is enforced.
     */
    public function testTuuidCannotBeReassigned(): void
    {
        $entity = new Scalar();
        $first  = Tuuid::generate();
        $entity->setTuuid($first);

        $this->expectException(\LogicException::class);
        $entity->setTuuid(Tuuid::generate());
    }
}
