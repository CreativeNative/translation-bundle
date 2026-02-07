<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\EntityTranslator;
use Tmi\TranslationBundle\Utils\AttributeHelper;

#[AllowMockObjectsWithoutExpectations]
class UnitTestCase extends TestCase
{
    protected const string TARGET_LOCALE = 'de_DE';

    protected EntityTranslator|null $translator = null;

    protected (MockObject&EntityManagerInterface)|null $entityManager = null;

    protected (Stub&EventDispatcherInterface)|null $eventDispatcherInterface = null;

    protected (MockObject&AttributeHelper)|null $attributeHelper = null;

    protected (MockObject&LoggerInterface)|null $logger = null;

    protected PropertyAccessor|null $propertyAccessor = null;

    protected function translator(): EntityTranslator
    {
        self::assertNotNull($this->translator, 'setUp() must run before accessing translator');

        return $this->translator;
    }

    protected function entityManager(): MockObject&EntityManagerInterface
    {
        self::assertNotNull($this->entityManager, 'setUp() must run before accessing entityManager');

        return $this->entityManager;
    }

    protected function eventDispatcher(): Stub&EventDispatcherInterface
    {
        self::assertNotNull($this->eventDispatcherInterface, 'setUp() must run before accessing eventDispatcher');

        return $this->eventDispatcherInterface;
    }

    protected function attributeHelper(): MockObject&AttributeHelper
    {
        self::assertNotNull($this->attributeHelper, 'setUp() must run before accessing attributeHelper');

        return $this->attributeHelper;
    }

    protected function logger(): MockObject&LoggerInterface
    {
        self::assertNotNull($this->logger, 'setUp() must run before accessing logger');

        return $this->logger;
    }

    protected function propertyAccessor(): PropertyAccessor
    {
        self::assertNotNull($this->propertyAccessor, 'setUp() must run before accessing propertyAccessor');

        return $this->propertyAccessor;
    }

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        // First create stubs/mocks for the core dependencies
        $this->eventDispatcherInterface = static::createStub(EventDispatcherInterface::class);
        $this->attributeHelper          = $this->createMock(AttributeHelper::class);
        $this->entityManager            = $this->createMock(EntityManagerInterface::class);
        $this->logger                   = $this->createMock(LoggerInterface::class);
        $this->propertyAccessor         = new PropertyAccessor();

        // Translator can be built afterwards, since it depends on the mocks
        $this->translator = $this->getTranslator($this->logger);
    }

    public function getTranslationArgs(\ReflectionProperty|null $prop = null, mixed $fallback = null): TranslationArgs
    {
        $args = new TranslationArgs($fallback, 'en_US', 'de_DE');
        if (null !== $prop) {
            $args->setProperty($prop);
        }

        return $args;
    }

    private function getTranslator(LoggerInterface|null $logger = null): EntityTranslator
    {
        // Create a stub Query object
        $queryStub = static::createStub(Query::class);
        $queryStub->method('getResult')->willReturn([]); // Always return empty array

        // Create a stub QueryBuilder with chainable methods
        $qbStub = static::createStub(QueryBuilder::class);
        $qbStub->method('select')->willReturnSelf();
        $qbStub->method('from')->willReturnSelf();
        $qbStub->method('where')->willReturnSelf();
        $qbStub->method('andWhere')->willReturnSelf();
        $qbStub->method('setParameter')->willReturnSelf();
        $qbStub->method('getQuery')->willReturn($queryStub);

        // Stub EntityManager to return our QueryBuilder
        $emStub = static::createStub(EntityManagerInterface::class);
        $emStub->method('createQueryBuilder')->willReturn($qbStub);

        return new EntityTranslator(
            'en_US',
            ['de_DE', 'en_US', 'it_IT'],
            $this->eventDispatcher(),
            $this->attributeHelper(),
            $emStub,
            $logger,
        );
    }
}
