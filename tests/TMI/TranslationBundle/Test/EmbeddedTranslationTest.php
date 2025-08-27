<?php

namespace TMI\TranslationBundle\Test;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Tests for embedded entities.
 */
class EmbeddedTranslationTest extends KernelTestCase
{
    const TARGET_LOCALE = 'en';

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    public function testItCanTranslateEmbeddedEntity()
    {
        $address = new Fixtures\AppTestBundle\Entity\Embedded\Address()
            ->setStreet('13 place Sophie Trébuchet')
            ->setCity('Nantes')
            ->setPostalCode('44000')
            ->setCountry('France')
        ;
        $entity  = new Fixtures\AppTestBundle\Entity\Embedded\Translatable()
            ->setAddress($address)
            ->setLocale('en')
        ;

        $this->em->persist($entity);

        $trans = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->em->persist($trans);

        $this->em->flush();

        $this->assertEquals($entity->getAddress(), $trans->getAddress());
    }

    public function testItCanEmptyEmbeddedEntity()
    {
        $address = new Fixtures\AppTestBundle\Entity\Embedded\Address()
            ->setStreet('13 place Sophie Trébuchet')
            ->setCity('Nantes')
            ->setPostalCode('44000')
            ->setCountry('France')
        ;
        $entity  = new Fixtures\AppTestBundle\Entity\Embedded\Translatable()
            ->setEmptyAddress($address)
            ->setLocale('en')
        ;

        $this->em->persist($entity);

        /** @var \TMI\TranslationBundle\Test\Fixtures\AppTestBundle\Entity\Embedded\Translatable $trans */
        $trans = $this->translator->translate($entity, self::TARGET_LOCALE);

        $this->em->persist($trans);

        $this->em->flush();

        $this->assertEquals($entity->getEmptyAddress(), $address);
        $this->assertEmpty($trans->getEmptyAddress());
    }
}
