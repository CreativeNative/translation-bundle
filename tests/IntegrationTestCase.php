<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Tmi\TranslationBundle\Doctrine\EventSubscriber\TranslatableEventSubscriber;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Type\TuuidType;
use Psr\Log\NullLogger;
use Tmi\TranslationBundle\Translation\EntityTranslator;
use Tmi\TranslationBundle\Translation\Handlers\EmbeddedHandler;
use Tmi\TranslationBundle\Utils\AttributeHelper;

class IntegrationTestCase extends KernelTestCase
{
    protected const string TARGET_LOCALE = 'de_DE';

    protected EntityTranslator|null $translator = null;

    protected EntityManagerInterface|null $entityManager = null;

    protected AttributeHelper|null $attributeHelper = null;

    protected static Container|null $container = null;

    protected function translator(): EntityTranslator
    {
        self::assertNotNull($this->translator, 'setUp() must run before accessing translator');

        return $this->translator;
    }

    protected function entityManager(): EntityManagerInterface
    {
        self::assertNotNull($this->entityManager, 'setUp() must run before accessing entityManager');

        return $this->entityManager;
    }

    protected function attributeHelper(): AttributeHelper
    {
        self::assertNotNull($this->attributeHelper, 'setUp() must run before accessing attributeHelper');

        return $this->attributeHelper;
    }

    /**
     * {@inheritDoc}
     *
     * @throws TypesException|\Doctrine\DBAL\Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        // --- TUuID Type registry ---
        if (!Type::hasType(TuuidType::NAME)) {
            Type::addType(TuuidType::NAME, TuuidType::class);
        }

        self::bootKernel();

        if (method_exists(self::class, 'getContainer')) {
            $container = self::getContainer();
        } elseif (property_exists(self::class, 'container') && null !== self::$container) {
            $container = self::$container;
        } else {
            $container = self::$kernel->getContainer();
        }

        if (null === $container) {
            self::fail('Container is null. Kernel boot failed.');
        }

        if ($container->has('doctrine.dbal.default_connection')) {
            $connection = $container->get('doctrine.dbal.default_connection');
            $platform   = $connection->getDatabasePlatform();
            $platform->registerDoctrineTypeMapping('tuuid', 'tuuid');
        }

        try {
            $this->entityManager = $container->get('doctrine.orm.entity_manager');
        } catch (ServiceNotFoundException) {
            self::fail('EntityManager service not found. Tried: doctrine.orm.entity_manager');
        }

        if ($container->has('doctrine.dbal.default_connection')) {
            $connection = $container->get('doctrine.dbal.default_connection');
            $platform   = $connection->getDatabasePlatform();
            $platform->registerDoctrineTypeMapping('tuuid', 'tuuid');
        }

        try {
            $this->translator = $container->get('tmi_translation.translation.entity_translator');
        } catch (ServiceNotFoundException) {
            self::fail('EntityTranslator service not found. Tried: tmi_translation.translation.entity_translator');
        }

        $this->translator->setLogger(new NullLogger());

        $embeddedHandler = $container->get(EmbeddedHandler::class);
        if ($embeddedHandler instanceof EmbeddedHandler) {
            $embeddedHandler->setLogger(new NullLogger());
        }

        try {
            $this->attributeHelper = $container->get('tmi_translation.utils.attribute_helper');
        } catch (ServiceNotFoundException) {
            self::fail('Attribute helper service not found. Tried: tmi_translation.utils.attribute_helper');
        }

        $subscriber = new TranslatableEventSubscriber(
            'en_US',
            $this->translator,
        );

        $eventManager = $this->entityManager->getEventManager();
        $eventManager->addEventSubscriber($subscriber);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        if (null !== $metadata) {
            $schemaTool = new SchemaTool($this->entityManager);

            try {
                $schemaTool->dropSchema($metadata);
            } catch (\Exception) {
            }

            $schemaTool->createSchema($metadata);
        }
    }

    #[\Override]
    final public function tearDown(): void
    {
        restore_exception_handler();

        if (null !== $this->entityManager && $this->entityManager->isOpen()) {
            $this->entityManager->close();
        }
        $this->translator = null;

        static::$container = null;
        static::$kernel    = null;

        parent::tearDown();
    }

    final public static function assertIsTranslation(
        TranslatableInterface $source,
        TranslatableInterface $translation,
        string $targetLocale,
    ): void {
        self::assertSame($targetLocale, $translation->getLocale());
        self::assertEquals($source->getTuuid(), $translation->getTuuid());

        // Only enforce "different instance" if targetLocale is different
        if ($source->getLocale() !== $targetLocale) {
            self::assertNotSame(
                spl_object_hash($source),
                spl_object_hash($translation),
                'Expected a cloned translation when target locale differs',
            );
        }
    }
}
