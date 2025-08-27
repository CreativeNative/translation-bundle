<?php

namespace TMI\TranslationBundle\Test;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Tests for embedded entities.
 */
class EmbeddedTranslationTest extends TestCase
{
    const TARGET_LOCALE = 'en';

    #[\Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    public function testItCanTranslateEmbeddedEntity(): void
    {
        $address = new \TMI\TranslationBundle\Fixtures\Entity\Embedded\Address()
            ->setStreet('13 place Sophie Trébuchet')
            ->setCity('Nantes')
            ->setPostalCode('44000')
            ->setCountry('France')
        ;
        $entity  = new \TMI\TranslationBundle\Fixtures\Entity\Embedded\Translatable()
            ->setAddress($address)
            ->setLocale('en')
        ;

        $this->entityManager->persist($entity);

        $trans = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->persist($trans);

        $this->entityManager->flush();

        $this->assertEquals($entity->getAddress(), $trans->getAddress());
    }

    public function testItCanEmptyEmbeddedEntity(): void
    {
        $address = new \TMI\TranslationBundle\Fixtures\Entity\Embedded\Address()
            ->setStreet('13 place Sophie Trébuchet')
            ->setCity('Nantes')
            ->setPostalCode('44000')
            ->setCountry('France')
        ;
        $entity  = new \TMI\TranslationBundle\Fixtures\Entity\Embedded\Translatable()
            ->setEmptyAddress($address)
            ->setLocale('en')
        ;

        $this->entityManager->persist($entity);

        /** @var \TMI\TranslationBundle\Fixtures\Entity\Embedded\Translatable $trans */
        $trans = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->entityManager->persist($trans);

        $this->entityManager->flush();

        $this->assertEquals($entity->getEmptyAddress(), $address);
        $this->assertEmpty($trans->getEmptyAddress());
    }
}
