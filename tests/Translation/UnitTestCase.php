<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation;

use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionProperty;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\EntityTranslator;
use Tmi\TranslationBundle\Utils\AttributeHelper;
use Doctrine\ORM\Query;

class UnitTestCase extends TestCase
{
    protected EntityTranslator|null $translator = null;

    protected EntityManagerInterface|null $entityManager = null;

    protected EventDispatcherInterface|null $eventDispatcherInterface = null;

    protected AttributeHelper|null $attributeHelper = null;

    protected PropertyAccessor|null $propertyAccessor = null;

    protected const string TARGET_LOCALE = 'de';

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        // First create mocks for the core dependencies
        $this->eventDispatcherInterface = $this->createMock(EventDispatcherInterface::class);
        $this->attributeHelper = $this->createMock(AttributeHelper::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->propertyAccessor = new PropertyAccessor();

        // Translator can be built afterwards, since it depends on the mocks
        $this->translator = $this->getTranslator();
    }

    private function getTranslator(): EntityTranslator
    {
        // Create a mock Query object
        /** @var Query&MockObject $queryMock */
        $queryMock = $this->createMock(Query::class);
        $queryMock->method('getResult')->willReturn([]); // Always return empty array

        // Create a mock QueryBuilder with chainable methods
        /** @var QueryBuilder&MockObject $qbMock */
        $qbMock = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'select',
                'from',
                'where',
                'andWhere',
                'setParameter',
                'getQuery',
            ])
            ->getMock();

        // Configure chainable methods
        $qbMock->method('select')->willReturnSelf();
        $qbMock->method('from')->willReturnSelf();
        $qbMock->method('where')->willReturnSelf();
        $qbMock->method('andWhere')->willReturnSelf();
        $qbMock->method('setParameter')->willReturnSelf();
        $qbMock->method('getQuery')->willReturn($queryMock);

        // Mock EntityManager to return our QueryBuilder
        /** @var EntityManagerInterface&MockObject $emMock */
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->method('createQueryBuilder')->willReturn($qbMock);

        return new EntityTranslator(
            'en',
            ['de', 'en', 'it'],
            $this->eventDispatcherInterface,
            $this->attributeHelper,
            $emMock
        );
    }

    public function getTranslationArgs(ReflectionProperty|null $prop = null, mixed $fallback = null): TranslationArgs
    {
        $args = new TranslationArgs($fallback, 'en', 'de');
        if ($prop !== null) {
            $args->setProperty($prop);
        }

        return $args;
    }
}
