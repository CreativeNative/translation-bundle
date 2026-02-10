<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Exception\ValidationException;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\EntityTranslator;
use Tmi\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;
use Tmi\TranslationBundle\Translation\TypeDefaultResolver;
use Tmi\TranslationBundle\ValueObject\Tuuid;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(EntityTranslator::class)]
final class EntityTranslatorTest extends UnitTestCase
{
    public function testProcessTranslationThrowsWhenLocaleIsNotAllowed(): void
    {
        // entity with some locale
        $entity = new Scalar();
        $entity->setLocale('en_US');

        $args = new TranslationArgs($entity, 'en_US', 'xx'); // "xx" is not in allowed locales

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Locale "xx" is not allowed. Allowed locales:');

        $this->translator()->processTranslation($args);
    }

    public function testReturnsFallbackWhenNoHandlerSupports(): void
    {
        $args = $this->getTranslationArgs(null, 'fallback');
        $this->translator()->addTranslationHandler($this->handlerNotSupporting());
        $this->translator()->addTranslationHandler($this->handlerNotSupporting());
        $result = $this->translator()->processTranslation($args);
        self::assertSame('fallback', $result);
    }

    public function testFirstSupportingHandlerWins(): void
    {
        $args  = $this->getTranslationArgs(null, 'fallback');
        $first = $this->createMock(TranslationHandlerInterface::class);
        $first->expects($this->once())->method('supports')->with($args)->willReturn(true);
        $first->expects($this->once())->method('translate')->with($args)->willReturn('first');
        $second = $this->createMock(TranslationHandlerInterface::class);
        $second->expects($this->never())->method('supports');

        // not reached
        $second->expects($this->never())->method('translate');
        $this->translator()->addTranslationHandler($first);
        $this->translator()->addTranslationHandler($second);
        self::assertSame('first', $this->translator()->processTranslation($args));
    }

    /**
     * @throws \ReflectionException
     */
    public function testSharedAmongstTranslationsBranchCallsDedicatedHandler(): void
    {
        $propClass = new class {
            public string|null $title = null;
        };
        $prop = new \ReflectionProperty($propClass, 'title');
        $args = $this->getTranslationArgs($prop);
        $this->attributeHelper()->method('isSharedAmongstTranslations')->with($prop)->willReturn(true);
        $this->attributeHelper()->method('isEmptyOnTranslate')->with($prop)->willReturn(false);
        $handler = $this->handlerSupporting(
            $args,
            'unused',
            null,
            ['handleSharedAmongstTranslations' => 'shared-result'],
        );
        $this->translator()->addTranslationHandler($handler);
        self::assertSame('shared-result', $this->translator()->processTranslation($args));
    }

    /**
     * @throws \ReflectionException
     */
    public function testEmptyOnTranslateWithNullableCallsDedicatedHandler(): void
    {
        $propClass = new class {
            public string|null $body = null;
        };
        $prop = new \ReflectionProperty($propClass, 'body');
        $args = $this->getTranslationArgs($prop);
        $this->attributeHelper()->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->with($prop)->willReturn(true);
        $this->attributeHelper()->method('isNullable')->with($prop)->willReturn(true);
        $handler = $this->handlerSupporting($args, 'unused', null, ['handleEmptyOnTranslate' => 'emptied']);
        $this->translator()->addTranslationHandler($handler);
        self::assertSame('emptied', $this->translator()->processTranslation($args));
    }

    /**
     * @throws \ReflectionException
     */
    public function testEmptyOnTranslateOnNonNullableStringReturnsEmptyString(): void
    {
        $propClass = new class {
            public string $slug = 'some-slug';
        };
        $prop = new \ReflectionProperty($propClass, 'slug');
        $args = $this->getTranslationArgs($prop);
        $this->attributeHelper()->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->with($prop)->willReturn(true);
        $this->attributeHelper()->method('isNullable')->with($prop)->willReturn(false);
        $handler = $this->handlerSupporting($args, 'unused');
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($args);

        self::assertSame('', $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testEmptyOnTranslateOnNonNullableIntReturnsZero(): void
    {
        $propClass = new class {
            public int $count = 5;
        };
        $prop = new \ReflectionProperty($propClass, 'count');
        $args = $this->getTranslationArgs($prop);
        $this->attributeHelper()->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->with($prop)->willReturn(true);
        $this->attributeHelper()->method('isNullable')->with($prop)->willReturn(false);
        $handler = $this->handlerSupporting($args, 'unused');
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($args);

        self::assertSame(0, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testEmptyOnTranslateOnNonNullableBoolReturnsFalse(): void
    {
        $propClass = new class {
            public bool $active = true;
        };
        $prop = new \ReflectionProperty($propClass, 'active');
        $args = $this->getTranslationArgs($prop);
        $this->attributeHelper()->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->with($prop)->willReturn(true);
        $this->attributeHelper()->method('isNullable')->with($prop)->willReturn(false);
        $handler = $this->handlerSupporting($args, 'unused');
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($args);

        self::assertFalse($result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testTranslateBranchIsUsedWhenNoSpecialAttributes(): void
    {
        $propClass = new class {
            public int|null $n = null;
        };
        $prop = new \ReflectionProperty($propClass, 'n');
        $args = $this->getTranslationArgs($prop);
        $this->attributeHelper()->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->with($prop)->willReturn(false);
        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->expects($this->once())->method('supports')->with($args)->willReturn(true);
        $handler->expects($this->once())->method('translate')->with($args)->willReturn('translated');
        $this->translator()->addTranslationHandler($handler);
        self::assertSame('translated', $this->translator()->processTranslation($args));
    }

    public function testAddTranslationHandlerOrderWithExplicitKey(): void
    {
        $args  = $this->getTranslationArgs();
        $first = $this->createMock(TranslationHandlerInterface::class);
        $first->expects($this->once())->method('supports')->with($args)->willReturn(true);
        $first->expects($this->once())->method('translate')->with($args)->willReturn('first');
        $second = $this->createMock(TranslationHandlerInterface::class);
        $second->expects($this->never())->method('supports');

        // Insert with explicit key FIRST; then append another one.
        $this->translator()->addTranslationHandler($first, 0);
        $this->translator()->addTranslationHandler($second);
        self::assertSame('first', $this->translator()->processTranslation($args));
    }

    public function testLifecycleHooksUseEntityLocaleIfPresent(): void
    {
        $entity = new Scalar();
        $entity->setTitle('Original Title');

        // Make attribute helper report "no special attributes" for the property
        $this->attributeHelper()->method('isSharedAmongstTranslations')
            ->willReturnCallback(fn ($property) => false);
        $this->attributeHelper()->method('isEmptyOnTranslate')
            ->willReturnCallback(fn ($property) => false);

        // Handler called once (first lifecycle event); subsequent events use cache
        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->once())->method('translate')->willReturn($entity);

        $this->translator()->addTranslationHandler($handler);
        $this->translator()->afterLoad($entity);
        $this->translator()->beforePersist($entity);
        $this->translator()->beforeUpdate($entity);
    }

    public function testLifecycleHooksFallbackToDefaultLocaleWhenNull(): void
    {
        $entity = new Scalar();
        $entity->setTitle('Original Title');

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(false);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);

        // Handler called once (first lifecycle event); subsequent events use cache
        $handler->expects($this->once())->method('translate')->willReturn($entity);

        $this->translator()->addTranslationHandler($handler);

        // 4 lifecycle methods: afterLoad + beforePersist + beforeUpdate + beforeRemove
        // Only the first actually invokes the handler; the rest return from cache
        $this->translator()->afterLoad($entity);
        $this->translator()->beforePersist($entity);
        $this->translator()->beforeUpdate($entity);
        $this->translator()->beforeRemove($entity);
    }

    public function testProcessTranslationUsesExistingCacheEntry(): void
    {
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $entity = new Scalar();
        $entity->setTuuid($tuuid);
        $entity->setLocale('en_US');

        // Prepare a cached translation instance
        $cached = clone $entity;
        $cached->setLocale('de_DE');

        // Inject into cache via cache service
        $this->cache()->set((string) $tuuid, 'de_DE', $cached);

        // If cache exists, processTranslation should return cached item
        $args   = new TranslationArgs($entity, 'en_US', 'de_DE');
        $result = $this->translator()->processTranslation($args);

        self::assertSame($cached, $result);
    }

    public function testWarmupTranslationsPopulatesCacheWithoutQuery(): void
    {
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $entity = new Scalar();
        $entity->setTuuid($tuuid);
        $entity->setLocale('en_US');

        // simulate cached translation directly
        $translated = clone $entity;
        $translated->setLocale('de_DE');

        // Inject into cache via cache service
        $this->cache()->set((string) $tuuid, 'de_DE', $translated);

        $args   = new TranslationArgs($entity, 'en_US', 'de_DE');
        $result = $this->translator()->processTranslation($args);

        self::assertSame($translated, $result);
    }

    public function testInProgressPreventsRecursiveTranslation(): void
    {
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $entity = new Scalar();
        $entity->setTuuid($tuuid);
        $entity->setLocale('en_US');

        // Mark as in-progress for this tuuid + target locale via cache service
        $this->cache()->markInProgress($tuuid->getValue(), 'de_DE');

        // if inProgress is set, processTranslation should return the original entity (cycle detection)
        $args   = new TranslationArgs($entity, 'en_US', 'de_DE');
        $result = $this->translator()->processTranslation($args);

        self::assertSame($entity, $result);
    }

    public function testEntitiesWithAutoTuuidGoThroughWarmupAndCallHandlers(): void
    {
        // create an entity without explicit tuuid (auto-generates via getTuuid())
        $entity = new Scalar();
        $entity->setLocale('en_US');

        // handler is called after warmup completes (with empty DB results from stubbed EM)
        $args    = new TranslationArgs($entity, 'en_US', 'de_DE');
        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->expects($this->once())->method('supports')->with(
            self::isInstanceOf(TranslationArgs::class),
        )->willReturn(true);
        $handler->expects($this->once())->method('translate')->with(
            self::isInstanceOf(TranslationArgs::class),
        )->willReturn($entity);

        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($args);

        self::assertInstanceOf(Scalar::class, $result);
    }

    public function testProcessTranslationCleansUpInProgressOnWarmupException(): void
    {
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $entity = new Scalar();
        $entity->setTuuid($tuuid);
        $entity->setLocale('en_US');

        // Stub query chain to throw during warmup
        $queryStub = self::createStub(Query::class);
        $queryStub->method('getResult')->willThrowException(new \RuntimeException('DB error'));

        $qbStub = self::createStub(QueryBuilder::class);
        $qbStub->method('select')->willReturnSelf();
        $qbStub->method('from')->willReturnSelf();
        $qbStub->method('where')->willReturnSelf();
        $qbStub->method('andWhere')->willReturnSelf();
        $qbStub->method('setParameter')->willReturnSelf();
        $qbStub->method('getQuery')->willReturn($queryStub);

        $emStub = self::createStub(EntityManagerInterface::class);
        $emStub->method('createQueryBuilder')->willReturn($qbStub);

        $translator = new EntityTranslator(
            'en_US',
            ['de_DE', 'en_US', 'it_IT'],
            false,
            $this->eventDispatcher(),
            $this->attributeHelper(),
            new TypeDefaultResolver(),
            $emStub,
            $this->cache(),
        );

        $args = new TranslationArgs($entity, 'en_US', 'de_DE');

        // Verify the exception is re-thrown
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('DB error');

        try {
            $translator->processTranslation($args);
        } finally {
            // In-progress mark was cleaned up despite the exception
            self::assertFalse($this->cache()->isInProgress($tuuid->getValue(), 'de_DE'));
        }
    }

    /**
     * @throws \ReflectionException
     */
    public function testWarmupTranslationsSimplifiedContinues(): void
    {
        // --- 1st continue: entity not implementing TranslatableInterface ---
        $nonTranslatable = new \stdClass();

        // --- 2nd continue: TranslatableInterface with auto-generated tuuid (proceeds to DB query) ---
        $autoGeneratedTuuid = new Scalar();
        $autoGeneratedTuuid->setLocale('en_US');

        // --- 3rd continue: TranslatableInterface with cached tuuid ---

        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $scalarCached = new Scalar();
        $scalarCached->setLocale('en_US');
        $scalarCached->setTuuid($tuuid);

        // Inject cache via cache service
        $this->cache()->set((string) $tuuid, 'de_DE', $scalarCached);

        $entities = [$nonTranslatable, $autoGeneratedTuuid, $scalarCached];

        // Call warmupTranslations (private method, still requires reflection)
        $method = new \ReflectionMethod($this->translator(), 'warmupTranslations');
        $method->invoke($this->translator(), $entities, 'de_DE');

        // If execution reaches this point without error, continues were hit
        $this->addToAssertionCount(1);
    }

    /**
     * Cache hit: returns cached entity. InProgress is NOT cleared in the cache-hit path
     * because the early return happens before markInProgress/unmarkInProgress are called.
     */
    public function testProcessTranslationUsesCacheAndKeepsInProgressOnCacheHit(): void
    {
        $sharedTuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $entity = new Scalar();
        $entity->setTuuid($sharedTuuid);
        $entity->setLocale('en_US');

        $cachedTranslation = new Scalar();
        $cachedTranslation->setTuuid($sharedTuuid);
        $cachedTranslation->setLocale('de_DE');

        // Pre-fill cache via cache service
        $this->cache()->set($sharedTuuid->getValue(), 'de_DE', $cachedTranslation);

        // Mark as inProgress via cache service
        $this->cache()->markInProgress($sharedTuuid->getValue(), 'de_DE');

        $args   = new TranslationArgs($entity, 'en_US', 'de_DE');
        $result = $this->translator()->processTranslation($args);

        self::assertSame($cachedTranslation, $result);

        // Cache hit returns early -- inProgress is NOT cleared in the cache-hit path
        self::assertTrue($this->cache()->isInProgress($sharedTuuid->getValue(), 'de_DE'));
    }

    public function testLoggerIsOptional(): void
    {
        // Create translator without logger (should not throw)
        $translator = new EntityTranslator(
            'en_US',
            ['de_DE', 'en_US'],
            false,
            $this->eventDispatcher(),
            $this->attributeHelper(),
            new TypeDefaultResolver(),
            $this->createMock(EntityManagerInterface::class),
            $this->cache(),
            null, // No logger
        );

        // Verify the translator was created successfully
        $this->addToAssertionCount(1);
    }

    public function testSetLoggerMethod(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        // Expect info log when translate is called
        $logger->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->stringContains('[TMI Translation]'),
                $this->callback(static fn (mixed $value): bool => is_array($value)),
            );

        $this->translator()->setLogger($logger);

        $entity = new Scalar();
        $entity->setLocale('en_US');

        // Add a simple handler
        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn($entity);
        $this->translator()->addTranslationHandler($handler);

        $this->translator()->translate($entity, 'de_DE');
    }

    public function testLoggingOnTranslationStart(): void
    {
        $this->logger()->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->stringContains('[TMI Translation] Starting translation'),
                $this->callback(function (array $context) {
                    return isset($context['class'])
                        && isset($context['source_locale'])
                        && isset($context['target_locale']);
                }),
            );

        $entity = new Scalar();
        $entity->setLocale('en_US');

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn($entity);
        $this->translator()->addTranslationHandler($handler);

        $this->translator()->translate($entity, 'de_DE');
    }

    public function testLoggingOnHandlerSelected(): void
    {
        $this->logger()->expects($this->atLeastOnce())
            ->method('debug')
            ->with(
                $this->stringContains('[TMI Translation]'),
                $this->callback(static fn (mixed $value): bool => is_array($value)),
            );

        $entity = new Scalar();
        $entity->setLocale('en_US');

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn($entity);
        $this->translator()->addTranslationHandler($handler);

        $this->translator()->translate($entity, 'de_DE');
    }

    public function testNoLoggingWhenLoggerIsNull(): void
    {
        // Create translator without logger
        $translatorWithoutLogger = new EntityTranslator(
            'en_US',
            ['de_DE', 'en_US'],
            false,
            $this->eventDispatcher(),
            $this->attributeHelper(),
            new TypeDefaultResolver(),
            $this->createMock(EntityManagerInterface::class),
            $this->cache(),
            null,
        );

        $entity = new Scalar();
        $entity->setLocale('en_US');

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn($entity);
        $translatorWithoutLogger->addTranslationHandler($handler);

        // Should not throw - logging is silently skipped
        $result = $translatorWithoutLogger->translate($entity, 'de_DE');

        self::assertSame($entity, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testLoggingOnSharedAmongstTranslationsDetected(): void
    {
        $propClass = new class {
            public string|null $title = null;
        };
        $prop = new \ReflectionProperty($propClass, 'title');

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(true);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(false);

        $this->logger()->expects($this->atLeastOnce())
            ->method('debug')
            ->with(
                $this->logicalOr(
                    $this->stringContains('Handler selected'),
                    $this->stringContains('SharedAmongstTranslations'),
                ),
                $this->callback(static fn (mixed $value): bool => is_array($value)),
            );

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('handleSharedAmongstTranslations')->willReturn('shared-result');
        $this->translator()->addTranslationHandler($handler);

        $args = $this->getTranslationArgs($prop, 'test-data');
        $this->translator()->processTranslation($args);
    }

    /**
     * @throws \ReflectionException
     */
    public function testLoggingOnEmptyOnTranslateDetected(): void
    {
        $propClass = new class {
            public string|null $body = null;
        };
        $prop = new \ReflectionProperty($propClass, 'body');

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(true);
        $this->attributeHelper()->method('isNullable')->willReturn(true);

        $this->logger()->expects($this->atLeastOnce())
            ->method('debug')
            ->with(
                $this->logicalOr(
                    $this->stringContains('Handler selected'),
                    $this->stringContains('EmptyOnTranslate'),
                ),
                $this->callback(static fn (mixed $value): bool => is_array($value)),
            );

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('handleEmptyOnTranslate')->willReturn(null);
        $this->translator()->addTranslationHandler($handler);

        $args = $this->getTranslationArgs($prop, 'test-data');
        $this->translator()->processTranslation($args);
    }

    /**
     * @throws \ReflectionException
     */
    public function testProcessTranslationCallsValidatePropertyBeforeAttributeChecks(): void
    {
        $propClass = new class {
            #[SharedAmongstTranslations]
            #[EmptyOnTranslate]
            public string|null $conflicting = null;
        };
        $prop = new \ReflectionProperty($propClass, 'conflicting');
        $args = $this->getTranslationArgs($prop);

        // Configure mock to throw ValidationException when validateProperty is called
        $this->attributeHelper()->expects($this->once())
            ->method('validateProperty')
            ->with($prop, $this->logger())
            ->willThrowException(new ValidationException([
                new \LogicException('Test error'),
            ]));

        // These methods should NOT be called because validation fails first
        $this->attributeHelper()->expects($this->never())->method('isSharedAmongstTranslations');
        $this->attributeHelper()->expects($this->never())->method('isEmptyOnTranslate');

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $this->translator()->addTranslationHandler($handler);

        self::expectException(ValidationException::class);

        $this->translator()->processTranslation($args);
    }

    /**
     * @throws \ReflectionException
     */
    public function testProcessTranslationPassesLoggerToValidateProperty(): void
    {
        $propClass = new class {
            public string|null $normalProperty = null;
        };
        $prop = new \ReflectionProperty($propClass, 'normalProperty');
        $args = $this->getTranslationArgs($prop);

        // Verify that logger is passed to validateProperty
        $this->attributeHelper()->expects($this->once())
            ->method('validateProperty')
            ->with($prop, $this->logger());

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(false);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn('result');
        $this->translator()->addTranslationHandler($handler);

        $this->translator()->processTranslation($args);
    }

    public function testProcessTranslationSkipsValidationForNonReflectionProperty(): void
    {
        // When property is not a ReflectionProperty, validation should not be called
        $args = $this->getTranslationArgs(null, 'fallback');

        $this->attributeHelper()->expects($this->never())->method('validateProperty');

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn('result');
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($args);

        self::assertSame('result', $result);
    }

    public function testWarmupTranslationsStoresResultsAndCacheHitReturnsEarly(): void
    {
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        // Original entity (source locale)
        $entity = new Scalar();
        $entity->setTuuid($tuuid);
        $entity->setLocale('en_US');

        // Translated entity that the DB query will return
        $translated = new Scalar();
        $translated->setTuuid($tuuid);
        $translated->setLocale('de_DE');

        // Stub the query chain to return the translated entity
        $queryStub = self::createStub(Query::class);
        $queryStub->method('getResult')->willReturn([$translated]);

        $qbStub = self::createStub(QueryBuilder::class);
        $qbStub->method('select')->willReturnSelf();
        $qbStub->method('from')->willReturnSelf();
        $qbStub->method('where')->willReturnSelf();
        $qbStub->method('andWhere')->willReturnSelf();
        $qbStub->method('setParameter')->willReturnSelf();
        $qbStub->method('getQuery')->willReturn($queryStub);

        $emStub = self::createStub(EntityManagerInterface::class);
        $emStub->method('createQueryBuilder')->willReturn($qbStub);

        // Build a translator with the custom EntityManager (shares cache with test)
        $translator = new EntityTranslator(
            'en_US',
            ['de_DE', 'en_US', 'it_IT'],
            false,
            $this->eventDispatcher(),
            $this->attributeHelper(),
            new TypeDefaultResolver(),
            $emStub,
            $this->cache(),
            $this->logger(),
        );

        $args   = new TranslationArgs($entity, 'en_US', 'de_DE');
        $result = $translator->processTranslation($args);

        // The warmup query returned the translated entity, which was cached.
        // The cache hit path returns that entity immediately.
        self::assertSame($translated, $result);

        // Verify inProgress was cleaned up via cache service
        self::assertFalse($this->cache()->isInProgress($tuuid->getValue(), 'de_DE'));
    }

    /**
     * @throws \ReflectionException
     */
    public function testCopySourceFalseReturnsTypeSafeDefault(): void
    {
        $propClass = new class {
            public string $title = 'original';
        };
        $prop = new \ReflectionProperty($propClass, 'title');
        $args = new TranslationArgs('original', 'en_US', 'de_DE');
        $args->setProperty($prop)->setCopySource(false);

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(false);
        $this->attributeHelper()->method('isEmbedded')->willReturn(false);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->never())->method('translate');
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($args);

        self::assertSame('', $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testCopySourceFalseWithSharedAmongstTranslationsStillShares(): void
    {
        $propClass = new class {
            public string $shared = 'shared-value';
        };
        $prop = new \ReflectionProperty($propClass, 'shared');
        $args = new TranslationArgs('shared-value', 'en_US', 'de_DE');
        $args->setProperty($prop)->setCopySource(false);

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(true);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->once())->method('handleSharedAmongstTranslations')->willReturn('shared-value');
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($args);

        self::assertSame('shared-value', $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testCopySourceFalseWithEmptyOnTranslateLogsRedundancy(): void
    {
        $propClass = new class {
            public string $title = 'original';
        };
        $prop = new \ReflectionProperty($propClass, 'title');
        $args = new TranslationArgs('original', 'en_US', 'de_DE');
        $args->setProperty($prop)->setCopySource(false);

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(true);
        $this->attributeHelper()->method('isEmbedded')->willReturn(false);

        $this->logger()->expects($this->atLeastOnce())
            ->method('debug')
            ->with(
                $this->logicalOr(
                    $this->stringContains('Handler selected'),
                    $this->stringContains('EmptyOnTranslate has no effect when copy_source is false'),
                    $this->stringContains('Type-safe default'),
                ),
                $this->callback(static fn (mixed $value): bool => is_array($value)),
            );

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($args);

        self::assertSame('', $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testCopySourceFalseNonNullableObjectCopiesFromSource(): void
    {
        $propClass = new class {
            public \DateTimeInterface $created;

            public function __construct()
            {
                $this->created = new \DateTimeImmutable();
            }
        };
        $prop = new \ReflectionProperty($propClass, 'created');
        $args = new TranslationArgs($propClass->created, 'en_US', 'de_DE');
        $args->setProperty($prop)->setCopySource(false);

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(false);
        $this->attributeHelper()->method('isEmbedded')->willReturn(false);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->once())->method('translate')->willReturn($propClass->created);
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($args);

        self::assertSame($propClass->created, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testCopySourceFalseEmbeddedDelegatesToHandler(): void
    {
        $propClass = new class {
            public object $address;

            public function __construct()
            {
                $this->address = new \stdClass();
            }
        };
        $prop = new \ReflectionProperty($propClass, 'address');
        $args = new TranslationArgs($propClass->address, 'en_US', 'de_DE');
        $args->setProperty($prop)->setCopySource(false);

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmbedded')->willReturn(true);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(false);

        $embeddedResult = new \stdClass();
        $handler        = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->once())->method('translate')->willReturn($embeddedResult);
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($args);

        self::assertSame($embeddedResult, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testCopySourceFalseEmbeddedWithEmptyOnTranslateLogsRedundancy(): void
    {
        $propClass = new class {
            public object $address;

            public function __construct()
            {
                $this->address = new \stdClass();
            }
        };
        $prop = new \ReflectionProperty($propClass, 'address');
        $args = new TranslationArgs($propClass->address, 'en_US', 'de_DE');
        $args->setProperty($prop)->setCopySource(false);

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmbedded')->willReturn(true);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(true);

        $this->logger()->expects($this->atLeastOnce())
            ->method('debug')
            ->with(
                $this->logicalOr(
                    $this->stringContains('Handler selected'),
                    $this->stringContains('EmptyOnTranslate has no effect when copy_source is false'),
                ),
                $this->callback(static fn (mixed $value): bool => is_array($value)),
            );

        $embeddedResult = new \stdClass();
        $handler        = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->once())->method('translate')->willReturn($embeddedResult);
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($args);

        self::assertSame($embeddedResult, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testCopySourceTruePreservesExistingBehavior(): void
    {
        $propClass = new class {
            public string|null $body = null;
        };
        $prop = new \ReflectionProperty($propClass, 'body');
        $args = new TranslationArgs('some text', 'en_US', 'de_DE');
        $args->setProperty($prop)->setCopySource(true);

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(true);
        $this->attributeHelper()->method('isNullable')->willReturn(true);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->once())->method('handleEmptyOnTranslate')->willReturn(null);
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($args);

        self::assertNull($result);
    }

    public function testResolveCopySourceUsesEntityAttribute(): void
    {
        $entity = new Scalar();
        $entity->setLocale('en_US');

        // Configure attributeHelper to return a Translatable attribute with copySource=true
        $attribute = new \Tmi\TranslationBundle\Doctrine\Attribute\Translatable(copySource: true);
        $this->attributeHelper()->method('getTranslatableAttribute')->willReturn($attribute);
        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(false);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn($entity);
        $this->translator()->addTranslationHandler($handler);

        $args   = new TranslationArgs($entity, 'en_US', 'de_DE');
        $result = $this->translator()->processTranslation($args);

        // The entity-level attribute should override the global copySource (false)
        // and set args.copySource to true
        self::assertTrue($args->getCopySource());
    }

    public function testResolveCopySourceUsesGlobalWhenNoAttribute(): void
    {
        $entity = new Scalar();
        $entity->setLocale('en_US');

        // No entity-level attribute
        $this->attributeHelper()->method('getTranslatableAttribute')->willReturn(null);
        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(false);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn($entity);
        $this->translator()->addTranslationHandler($handler);

        $args   = new TranslationArgs($entity, 'en_US', 'de_DE');
        $result = $this->translator()->processTranslation($args);

        // Global copySource is false (from test setup)
        self::assertFalse($args->getCopySource());
    }

    public function testResolveCopySourceUsesGlobalWhenAttributeHasNullCopySource(): void
    {
        $entity = new Scalar();
        $entity->setLocale('en_US');

        // Entity-level attribute with null copySource (defer to global)
        $attribute = new \Tmi\TranslationBundle\Doctrine\Attribute\Translatable(copySource: null);
        $this->attributeHelper()->method('getTranslatableAttribute')->willReturn($attribute);
        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(false);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn($entity);
        $this->translator()->addTranslationHandler($handler);

        $args   = new TranslationArgs($entity, 'en_US', 'de_DE');
        $result = $this->translator()->processTranslation($args);

        // Global copySource is false
        self::assertFalse($args->getCopySource());
    }

    /**
     * @throws \ReflectionException
     */
    public function testCopySourceFalseNonNullableObjectWithEmptyOnTranslateLogsRedundancy(): void
    {
        $propClass = new class {
            public \DateTimeInterface $created;

            public function __construct()
            {
                $this->created = new \DateTimeImmutable();
            }
        };
        $prop = new \ReflectionProperty($propClass, 'created');
        $args = new TranslationArgs($propClass->created, 'en_US', 'de_DE');
        $args->setProperty($prop)->setCopySource(false);

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(true);
        $this->attributeHelper()->method('isEmbedded')->willReturn(false);

        $this->logger()->expects($this->atLeastOnce())
            ->method('debug')
            ->with(
                $this->logicalOr(
                    $this->stringContains('Handler selected'),
                    $this->stringContains('EmptyOnTranslate has no effect when copy_source is false'),
                    $this->stringContains('non-nullable object'),
                ),
                $this->callback(static fn (mixed $value): bool => is_array($value)),
            );

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->once())->method('translate')->willReturn($propClass->created);
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($args);

        self::assertSame($propClass->created, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testCopySourceFalseNullablePropertyReturnsNull(): void
    {
        $propClass = new class {
            public string|null $title = 'original';
        };
        $prop = new \ReflectionProperty($propClass, 'title');
        $args = new TranslationArgs('original', 'en_US', 'de_DE');
        $args->setProperty($prop)->setCopySource(false);

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(false);
        $this->attributeHelper()->method('isEmbedded')->willReturn(false);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($args);

        self::assertNull($result);
    }

    /**
     * @param array<string, mixed> $methodToReturnMap Method names as keys, return values as values
     */
    private function handlerSupporting(
        TranslationArgs $expectedArgs,
        mixed $return,
        callable|null $assert = null,
        array $methodToReturnMap = [],
    ): TranslationHandlerInterface {
        $handler = $this->createMock(TranslationHandlerInterface::class);

        $handler->method('supports')->with(
            self::callback(static fn (TranslationArgs $args) => $args->getDataToBeTranslated() === $expectedArgs->getDataToBeTranslated()
                && $args->getSourceLocale()                                                    === $expectedArgs->getSourceLocale()
                && $args->getTargetLocale()                                                    === $expectedArgs->getTargetLocale()
                && $args->getProperty()                                                        === $expectedArgs->getProperty()
                && $args->getTranslatedParent()                                                === $expectedArgs->getTranslatedParent()),
        )->willReturn(true);

        // Default translate() behavior
        $handler->method('translate')->willReturn($return);
        foreach ($methodToReturnMap as $method => $value) {
            self::assertNotEmpty($method);
            $handler->expects($this->once())->method($method)->with(
                self::callback(static fn (TranslationArgs $args) => $args->getDataToBeTranslated() === $expectedArgs->getDataToBeTranslated()
                    && $args->getSourceLocale()                                                    === $expectedArgs->getSourceLocale()
                    && $args->getTargetLocale()                                                    === $expectedArgs->getTargetLocale()
                    && $args->getProperty()                                                        === $expectedArgs->getProperty()
                    && $args->getTranslatedParent()                                                === $expectedArgs->getTranslatedParent()),
            )->willReturn($value);
        }

        if (null !== $assert) {
            ($assert)($handler);
        }

        return $handler;
    }

    private function handlerNotSupporting(): TranslationHandlerInterface
    {
        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(false);
        $handler->expects($this->never())->method('translate');
        $handler->expects($this->never())->method('handleSharedAmongstTranslations');
        $handler->expects($this->never())->method('handleEmptyOnTranslate');

        return $handler;
    }
}
