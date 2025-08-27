<?php

namespace TMI\TranslationBundle\Test;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use TMI\TranslationBundle\Translation\EntityTranslator;

abstract class AbstractBaseTest extends KernelTestCase
{

    protected EntityTranslator|null $translator;

    protected EntityManagerInterface|null $entityManager;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
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
            $this->fail('Container is null. Kernel boot failed.');
        }

        $this->initializeEntityManager($container);
        $this->initializeTranslator($container);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager = null;
        $this->translator = null;
    }

    protected function initializeEntityManager($container): void
    {
        $servicesToTry = [
            'doctrine.orm.entity_manager',
            EntityManagerInterface::class,
            EntityManager::class
        ];

        foreach ($servicesToTry as $service) {
            try {
                $this->entityManager = $container->get($service);
                return;
            } catch (ServiceNotFoundException $e) {
                continue;
            }
        }

        $this->fail(sprintf(
            'EntityManager service not found. Tried: %s. ' .
            'Check if DoctrineBundle is properly configured and the entity manager service is available.',
            implode(', ', $servicesToTry)
        ));
    }

    protected function initializeTranslator($container): void
    {
        $servicesToTry = [
            'tmi_translation.translation.entity_translator',
            EntityTranslator::class
        ];

        foreach ($servicesToTry as $service) {
            try {
                $this->translator = $container->get($service);
                return;
            } catch (ServiceNotFoundException $e) {
                continue;
            }
        }

        // Debug-Informationen sammeln
        $availableServices = array_keys($container->getServiceIds());
        $translationServices = array_filter($availableServices, function ($service) {
            return stripos($service, 'translation') !== false ||
                stripos($service, 'translator') !== false ||
                stripos($service, 'tmi') !== false;
        });

        $this->fail(sprintf(
            'EntityTranslator service not found. Tried: %s. ' .
            'Available relevant services: %s. ' .
            'Check your bundle configuration and service registration.',
            implode(', ', $servicesToTry),
            implode(', ', $translationServices)
        ));
    }
}
