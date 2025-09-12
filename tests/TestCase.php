<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\EntityTranslator;
use Tmi\TranslationBundle\Utils\AttributeHelper;

class TestCase extends KernelTestCase
{
    private static Container|null $container = null;

    protected EntityTranslator|null $translator = null;

    protected EntityManagerInterface|null $entityManager = null;

    protected AttributeHelper|null $attributeHelper = null;

    protected const string TARGET_LOCALE = 'en';

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        if (method_exists(self::class, 'getContainer')) {
            $container = self::getContainer();
        } elseif (property_exists(self::class, 'container') && self::$container !== null) {
            $container = self::$container;
        } else {
            $container = self::$kernel->getContainer();
        }

        if ($container === null) {
            self::fail('Container is null. Kernel boot failed.');
        }

        try {
            $this->entityManager = $container->get('doctrine.orm.entity_manager');
        } catch (ServiceNotFoundException) {
            self::fail('EntityManager service not found. Tried: doctrine.orm.entity_manager');
        }

        try {
            $this->translator = $container->get('tmi_translation.translation.entity_translator');
        } catch (ServiceNotFoundException) {
            self::fail('EntityTranslator service not found. Tried: tmi_translation.translation.entity_translator');
        }

        try {
            $this->attributeHelper = $container->get('tmi_translation.utils.attribute_helper');
        } catch (ServiceNotFoundException) {
            self::fail('Attribute helper service not found. Tried: tmi_translation.utils.attribute_helper');
        }


        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        if ($metadata !== null) {
            $schemaTool = new SchemaTool($this->entityManager);

            try {
                $schemaTool->dropSchema($metadata);
            } catch (Exception) {
            }

            $schemaTool->createSchema($metadata);
        }
    }

    final public static function assertIsTranslation(
        TranslatableInterface $source,
        TranslatableInterface $translation,
        string $targetLocale
    ): void {
        self::assertSame($targetLocale, $translation->getLocale());
        self::assertEquals($source->getTuuid(), $translation->getTuuid());
        self::assertNotSame(spl_object_hash($source), spl_object_hash($translation));
    }

    final public function tearDown(): void
    {
        restore_exception_handler();

        if ($this->entityManager->isOpen()) {
            $this->entityManager->close();
        }
        $this->translator = null;

        static::$container = null;
        static::$kernel = null;

        parent::tearDown();
    }
}
