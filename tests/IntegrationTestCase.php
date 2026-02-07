<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Tmi\TranslationBundle\Doctrine\EventSubscriber\TranslatableEventSubscriber;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Type\TuuidType;
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

        $container = self::getContainer();

        $this->registerTuuidTypeMapping($container);

        $entityManager = $container->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager, 'EntityManager service must implement EntityManagerInterface');
        $this->entityManager = $entityManager;

        $translator = $container->get('tmi_translation.translation.entity_translator');
        self::assertInstanceOf(EntityTranslator::class, $translator, 'EntityTranslator service must be an EntityTranslator instance');
        $this->translator = $translator;

        $this->translator->setLogger(new NullLogger());

        $embeddedHandler = $container->get(EmbeddedHandler::class);
        if ($embeddedHandler instanceof EmbeddedHandler) {
            $embeddedHandler->setLogger(new NullLogger());
        }

        $attributeHelper = $container->get('tmi_translation.utils.attribute_helper');
        self::assertInstanceOf(AttributeHelper::class, $attributeHelper, 'Attribute helper service must be an AttributeHelper instance');
        $this->attributeHelper = $attributeHelper;

        $translator    = $this->translator();
        $entityManager = $this->entityManager();

        $subscriber = new TranslatableEventSubscriber(
            'en_US',
            $translator,
        );

        $eventManager = $entityManager->getEventManager();
        $eventManager->addEventSubscriber($subscriber);

        $metadata   = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);

        try {
            $schemaTool->dropSchema($metadata);
        } catch (\Exception) {
        }

        $schemaTool->createSchema($metadata);
    }

    private function registerTuuidTypeMapping(ContainerInterface $container): void
    {
        if (!$container->has('doctrine.dbal.default_connection')) {
            return;
        }

        $connection = $container->get('doctrine.dbal.default_connection');
        if (!$connection instanceof Connection) {
            return;
        }

        $platform = $connection->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('tuuid', 'tuuid');
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
