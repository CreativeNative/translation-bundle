<?php

namespace TMI\TranslationBundle\Test;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use TMI\TranslationBundle\Translation\EntityTranslator;
use Doctrine\ORM\Tools\SchemaTool;

class TestCase extends KernelTestCase
{
    private static $container;

    protected EntityTranslator|null $translator = null;

    protected EntityManagerInterface|null $entityManager = null;

    /** @var callable|null */
    private $previousExceptionHandler;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->previousExceptionHandler = set_exception_handler(null);

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
            $this->fail('Container is null. Kernel boot failed.');
        }

        try {
            $this->entityManager = $container->get('doctrine.orm.entity_manager');
        } catch (ServiceNotFoundException) {
            $this->fail('EntityManager service not found. Tried: doctrine.orm.entity_manager.');
        }

        try {
            $this->translator = $container->get('tmi_translation.translation.entity_translator',);
        } catch (ServiceNotFoundException) {
            $this->fail('EntityTranslator service not found. Tried: tmi_translation.translation.entity_translator.');
        }

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        if (!empty($metadata)) {
            $schemaTool = new SchemaTool($this->entityManager);

            try {
                $schemaTool->dropSchema($metadata);
            } catch (\Exception $e) {

            }

            $schemaTool->createSchema($metadata);
        }
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->previousExceptionHandler !== null) {
            set_exception_handler($this->previousExceptionHandler);
        }

        parent::tearDown();

        $this->entityManager->clear();
        $this->entityManager = null;
        $this->translator = null;
    }
}
