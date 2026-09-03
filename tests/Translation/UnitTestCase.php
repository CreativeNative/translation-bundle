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
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Cache\InMemoryTranslationCache;
use Tmi\TranslationBundle\Translation\EntityTranslator;
use Tmi\TranslationBundle\Translation\Handlers\DoctrineObjectHandler;
use Tmi\TranslationBundle\Translation\Handlers\TranslatableEntityHandler;
use Tmi\TranslationBundle\Translation\TypeDefaultResolver;
use Tmi\TranslationBundle\Utils\AttributeHelper;

#[AllowMockObjectsWithoutExpectations]
class UnitTestCase extends TestCase
{
    protected const string TARGET_LOCALE = 'de_DE';

    protected EntityTranslator|null $translator = null;

    protected InMemoryTranslationCache|null $cache = null;

    protected (MockObject&EntityManagerInterface)|null $entityManager = null;

    protected (Stub&EventDispatcherInterface)|null $eventDispatcherInterface = null;

    protected (MockObject&AttributeHelper)|null $attributeHelper = null;

    protected (MockObject&LoggerInterface)|null $logger = null;

    protected PropertyAccessor|null $propertyAccessor = null;

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
        $this->cache                    = new InMemoryTranslationCache();

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

    protected function cache(): InMemoryTranslationCache
    {
        self::assertNotNull($this->cache, 'setUp() must run before accessing cache');

        return $this->cache;
    }

    /**
     * A chainable QueryBuilder stub (select/from/where/andWhere/setParameter/getQuery)
     * whose query always returns $results -- the shape LocaleVariantFinder builds
     * internally for its cross-locale lookups, without exercising real Doctrine
     * query building.
     *
     * @param list<TranslatableInterface> $results
     */
    protected function queryBuilderReturning(array $results): QueryBuilder
    {
        $queryStub = static::createStub(Query::class);
        $queryStub->method('getResult')->willReturn($results);

        $qbStub = static::createStub(QueryBuilder::class);
        $qbStub->method('select')->willReturnSelf();
        $qbStub->method('from')->willReturnSelf();
        $qbStub->method('where')->willReturnSelf();
        $qbStub->method('andWhere')->willReturnSelf();
        $qbStub->method('setParameter')->willReturnSelf();
        $qbStub->method('getQuery')->willReturn($queryStub);

        return $qbStub;
    }

    /**
     * A LocaleVariantFinder backed by a disposable EntityManager stub whose only
     * query always returns $results.
     *
     * @param list<TranslatableInterface> $results
     */
    protected function localeVariantFinder(array $results = []): LocaleVariantFinder
    {
        $emStub = static::createStub(EntityManagerInterface::class);
        $emStub->method('createQueryBuilder')->willReturn($this->queryBuilderReturning($results));

        return new LocaleVariantFinder($emStub);
    }

    /**
     * A real TranslatableEntityHandler built on the shared entityManager()/translator()/
     * propertyAccessor() mocks -- the same clone-plus-pipeline entity handler that
     * BidirectionalOneToOneHandler and BidirectionalManyToOneHandler now delegate the
     * association target's clone to. Stub entityManager()->createQueryBuilder() (via
     * queryBuilderReturning()) to control what its LocaleVariantFinder reports as the
     * existing variant, if any.
     */
    protected function translatableEntityHandler(): TranslatableEntityHandler
    {
        return new TranslatableEntityHandler(
            new LocaleVariantFinder($this->entityManager()),
            new DoctrineObjectHandler($this->entityManager(), $this->translator(), $this->propertyAccessor()),
            new AttributeHelper(),
        );
    }

    private function getTranslator(LoggerInterface|null $logger = null): EntityTranslator
    {
        return new EntityTranslator(
            'en_US',
            ['de_DE', 'en_US', 'it_IT'],
            false,
            $this->eventDispatcher(),
            $this->attributeHelper(),
            new TypeDefaultResolver(),
            static::createStub(EntityManagerInterface::class),
            $this->cache(),
            $this->localeVariantFinder(),
            $logger,
        );
    }
}
