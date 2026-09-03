<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\Proxy;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Exception\ValidationException;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Seeding\EmptySeeded;
use Tmi\TranslationBundle\Translation\Context\EntityTranslationContext;
use Tmi\TranslationBundle\Translation\Context\PropertyTranslationContext;
use Tmi\TranslationBundle\Translation\Context\TranslationContext;
use Tmi\TranslationBundle\Translation\EntityTranslator;
use Tmi\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;
use Tmi\TranslationBundle\Translation\TypeDefaultResolver;
use Tmi\TranslationBundle\Utils\AttributeHelper;
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

        $context = new EntityTranslationContext($entity, 'en_US', 'xx'); // "xx" is not in allowed locales

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Locale "xx" is not allowed. Allowed locales:');

        $this->translator()->processTranslation($context);
    }

    public function testReturnsFallbackWhenNoHandlerSupports(): void
    {
        $context = $this->propertyContext('fallback');
        $this->translator()->addTranslationHandler($this->handlerNotSupporting());
        $this->translator()->addTranslationHandler($this->handlerNotSupporting());
        $result = $this->translator()->processTranslation($context);
        self::assertSame('fallback', $result);
    }

    public function testFirstSupportingHandlerWins(): void
    {
        $context = $this->propertyContext('fallback');
        $first   = $this->createMock(TranslationHandlerInterface::class);
        $first->expects($this->once())->method('supports')->with($context)->willReturn(true);
        $first->expects($this->once())->method('translate')->with($context)->willReturn('first');
        $second = $this->createMock(TranslationHandlerInterface::class);
        $second->expects($this->never())->method('supports');

        // not reached
        $second->expects($this->never())->method('translate');
        $this->translator()->addTranslationHandler($first);
        $this->translator()->addTranslationHandler($second);
        self::assertSame('first', $this->translator()->processTranslation($context));
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
        $this->attributeHelper()->method('isSharedAmongstTranslations')->with($prop)->willReturn(true);
        $this->attributeHelper()->method('isEmptyOnTranslate')->with($prop)->willReturn(false);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->once())->method('translate')->with(
            self::callback(static fn (TranslationContext $c): bool => $c->isShared() && !$c->isEmpty() && 'unused' === $c->getSubject()),
        )->willReturn('shared-result');
        $this->translator()->addTranslationHandler($handler);

        $context = $this->propertyContext('unused', $prop);
        self::assertSame('shared-result', $this->translator()->processTranslation($context));
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
        $this->attributeHelper()->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->with($prop)->willReturn(true);
        $this->attributeHelper()->method('isNullable')->with($prop)->willReturn(true);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->once())->method('translate')->with(
            self::callback(static fn (TranslationContext $c): bool => $c->isEmpty() && !$c->isShared()),
        )->willReturn('emptied');
        $this->translator()->addTranslationHandler($handler);

        $context = $this->propertyContext('unused', $prop);
        self::assertSame('emptied', $this->translator()->processTranslation($context));
    }

    /**
     * @throws \ReflectionException
     */
    public function testEmptyOnTranslateOnNonNullableStringReturnsEmptyString(): void
    {
        $propClass = new class {
            public string $slug = 'some-slug';
        };
        $prop    = new \ReflectionProperty($propClass, 'slug');
        $context = $this->propertyContext('unused', $prop);
        $this->attributeHelper()->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->with($prop)->willReturn(true);
        $this->attributeHelper()->method('isNullable')->with($prop)->willReturn(false);
        $handler = $this->handlerSupporting($context, 'unused');
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($context);

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
        $prop    = new \ReflectionProperty($propClass, 'count');
        $context = $this->propertyContext('unused', $prop);
        $this->attributeHelper()->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->with($prop)->willReturn(true);
        $this->attributeHelper()->method('isNullable')->with($prop)->willReturn(false);
        $handler = $this->handlerSupporting($context, 'unused');
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($context);

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
        $prop    = new \ReflectionProperty($propClass, 'active');
        $context = $this->propertyContext('unused', $prop);
        $this->attributeHelper()->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->with($prop)->willReturn(true);
        $this->attributeHelper()->method('isNullable')->with($prop)->willReturn(false);
        $handler = $this->handlerSupporting($context, 'unused');
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($context);

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
        $prop    = new \ReflectionProperty($propClass, 'n');
        $context = $this->propertyContext('unused', $prop);
        $this->attributeHelper()->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->with($prop)->willReturn(false);
        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->expects($this->once())->method('supports')->with($context)->willReturn(true);
        $handler->expects($this->once())->method('translate')->with($context)->willReturn('translated');
        $this->translator()->addTranslationHandler($handler);
        self::assertSame('translated', $this->translator()->processTranslation($context));
    }

    public function testAddTranslationHandlerOrderWithExplicitKey(): void
    {
        $context = $this->propertyContext('fallback');
        $first   = $this->createMock(TranslationHandlerInterface::class);
        $first->expects($this->once())->method('supports')->with($context)->willReturn(true);
        $first->expects($this->once())->method('translate')->with($context)->willReturn('first');
        $second = $this->createMock(TranslationHandlerInterface::class);
        $second->expects($this->never())->method('supports');

        // Insert with explicit key FIRST; then append another one.
        $this->translator()->addTranslationHandler($first, 0);
        $this->translator()->addTranslationHandler($second);
        self::assertSame('first', $this->translator()->processTranslation($context));
    }

    /**
     * Negative proof for #19: translate() used to log "Starting translation"
     * unconditionally, before processTranslation() ever reached the identity
     * check. Calling translate($entity, $entity->getLocale()) -- the exact
     * shape the old afterLoad/beforePersist/beforeUpdate/beforeRemove hooks
     * used -- is a no-op by construction and must produce no log entry.
     */
    public function testIdentityTranslateDoesNotLog(): void
    {
        $entity = new Scalar();
        $entity->setTitle('Original Title');
        $entity->setLocale('en_US');

        $this->logger()->expects($this->never())->method('info');

        $result = $this->translator()->translate($entity, 'en_US');

        self::assertSame($entity, $result);
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
        $context = new EntityTranslationContext($entity, 'en_US', 'de_DE');
        $result  = $this->translator()->processTranslation($context);

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

        $context = new EntityTranslationContext($entity, 'en_US', 'de_DE');
        $result  = $this->translator()->processTranslation($context);

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
        $context = new EntityTranslationContext($entity, 'en_US', 'de_DE');
        $result  = $this->translator()->processTranslation($context);

        self::assertSame($entity, $result);
    }

    public function testEntitiesWithAutoTuuidGoThroughWarmupAndCallHandlers(): void
    {
        // create an entity without explicit tuuid (auto-generates via getTuuid())
        $entity = new Scalar();
        $entity->setLocale('en_US');

        // handler is called after warmup completes (with empty DB results from stubbed EM)
        $context = new EntityTranslationContext($entity, 'en_US', 'de_DE');
        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->expects($this->once())->method('supports')->with(
            self::isInstanceOf(TranslationContext::class),
        )->willReturn(true);
        $handler->expects($this->once())->method('translate')->with(
            self::isInstanceOf(TranslationContext::class),
        )->willReturn($entity);

        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($context);

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
            new LocaleVariantFinder($emStub),
        );

        $context = new EntityTranslationContext($entity, 'en_US', 'de_DE');

        // Verify the exception is re-thrown
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('DB error');

        try {
            $translator->processTranslation($context);
        } finally {
            // In-progress mark was cleaned up despite the exception
            self::assertFalse($this->cache()->isInProgress($tuuid->getValue(), 'de_DE'));
        }
    }

    public function testHigherPriorityHandlerRunsFirstRegardlessOfRegistrationOrder(): void
    {
        $context = $this->propertyContext('fallback');

        $low = $this->createMock(TranslationHandlerInterface::class);
        $low->method('supports')->willReturn(true);
        $low->expects($this->never())->method('translate');

        $high = $this->createMock(TranslationHandlerInterface::class);
        $high->method('supports')->willReturn(true);
        $high->expects($this->once())->method('translate')->willReturn('high');

        // Registered last, but its priority must put it in front of the broad handler
        $this->translator()->addTranslationHandler($low, 10);
        $this->translator()->addTranslationHandler($high, 75);

        self::assertSame('high', $this->translator()->processTranslation($context));
    }

    public function testHandlersSharingAPriorityKeepRegistrationOrder(): void
    {
        $context = $this->propertyContext('fallback');

        $first = $this->createMock(TranslationHandlerInterface::class);
        $first->method('supports')->willReturn(true);
        $first->expects($this->once())->method('translate')->willReturn('first');

        $second = $this->createMock(TranslationHandlerInterface::class);
        $second->method('supports')->willReturn(true);
        $second->expects($this->never())->method('translate');

        // Same priority must not let one handler displace the other
        $this->translator()->addTranslationHandler($first, 50);
        $this->translator()->addTranslationHandler($second, 50);

        self::assertSame('first', $this->translator()->processTranslation($context));
    }

    public function testUnprioritisedHandlerRunsAfterAPrioritisedOne(): void
    {
        $context = $this->propertyContext('fallback');

        $prioritised = $this->createMock(TranslationHandlerInterface::class);
        $prioritised->method('supports')->willReturn(true);
        $prioritised->expects($this->once())->method('translate')->willReturn('prioritised');

        $plain = $this->createMock(TranslationHandlerInterface::class);
        $plain->method('supports')->willReturn(true);
        $plain->expects($this->never())->method('translate');

        $this->translator()->addTranslationHandler($plain);
        $this->translator()->addTranslationHandler($prioritised, 5);

        self::assertSame('prioritised', $this->translator()->processTranslation($context));
    }

    public function testProcessTranslationCleansUpInProgressWhenHandlerThrows(): void
    {
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $entity = new Scalar();
        $entity->setTuuid($tuuid);
        $entity->setLocale('en_US');

        $throwing = $this->createMock(TranslationHandlerInterface::class);
        $throwing->method('supports')->willReturn(true);
        $throwing->method('translate')->willThrowException(new \RuntimeException('handler exploded'));

        $this->translator()->addTranslationHandler($throwing);

        try {
            $this->translator()->processTranslation(new EntityTranslationContext($entity, 'en_US', 'de_DE'));
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            self::assertSame('handler exploded', $e->getMessage());
        }

        // The failure must not leave the tuuid/locale flagged -- a stale flag would make
        // every later translate() silently return the untranslated entity.
        self::assertFalse($this->cache()->isInProgress($tuuid->getValue(), 'de_DE'));
    }

    public function testTranslationWorksAfterAPreviousHandlerFailure(): void
    {
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $entity = new Scalar();
        $entity->setTuuid($tuuid);
        $entity->setLocale('en_US');

        $translated = new Scalar();
        $translated->setTuuid($tuuid);
        $translated->setLocale('de_DE');

        $alreadyFailed = false;

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturnCallback(
            static function () use (&$alreadyFailed, $translated): Scalar {
                if (!$alreadyFailed) {
                    $alreadyFailed = true;

                    throw new \RuntimeException('transient failure');
                }

                return $translated;
            },
        );

        $this->translator()->addTranslationHandler($handler);

        try {
            $this->translator()->translate($entity, 'de_DE');
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException) {
            // expected -- first attempt fails
        }

        // Second attempt must not hit cycle detection and silently return the source entity
        self::assertSame($translated, $this->translator()->translate($entity, 'de_DE'));
    }

    public function testProcessTranslationCleansUpInProgressWhenNoHandlerMatches(): void
    {
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $entity = new Scalar();
        $entity->setTuuid($tuuid);
        $entity->setLocale('en_US');

        $this->translator()->addTranslationHandler($this->handlerNotSupporting());

        $result = $this->translator()->processTranslation(new EntityTranslationContext($entity, 'en_US', 'de_DE'));

        self::assertSame($entity, $result);
        self::assertFalse($this->cache()->isInProgress($tuuid->getValue(), 'de_DE'));
    }

    public function testProcessTranslationCleansUpInProgressWhenHandlerReturnsNonTranslatable(): void
    {
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $entity = new Scalar();
        $entity->setTuuid($tuuid);
        $entity->setLocale('en_US');

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn('not-an-entity');

        $this->translator()->addTranslationHandler($handler);

        self::assertSame(
            'not-an-entity',
            $this->translator()->processTranslation(new EntityTranslationContext($entity, 'en_US', 'de_DE')),
        );
        self::assertFalse($this->cache()->isInProgress($tuuid->getValue(), 'de_DE'));
    }

    public function testPreloadSimplifiedContinues(): void
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

        $this->translator()->preload($entities, 'de_DE');

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

        $context = new EntityTranslationContext($entity, 'en_US', 'de_DE');
        $result  = $this->translator()->processTranslation($context);

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
            $this->localeVariantFinder(),
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
            $this->localeVariantFinder(),
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
        $handler->method('translate')->willReturn('shared-result');
        $this->translator()->addTranslationHandler($handler);

        $context = $this->propertyContext('test-data', $prop);
        $this->translator()->processTranslation($context);
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
        $handler->method('translate')->willReturn(null);
        $this->translator()->addTranslationHandler($handler);

        $context = $this->propertyContext('test-data', $prop);
        $this->translator()->processTranslation($context);
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
        $prop    = new \ReflectionProperty($propClass, 'conflicting');
        $context = $this->propertyContext('unused', $prop);

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

        $this->translator()->processTranslation($context);
    }

    /**
     * @throws \ReflectionException
     */
    public function testProcessTranslationPassesLoggerToValidateProperty(): void
    {
        $propClass = new class {
            public string|null $normalProperty = null;
        };
        $prop    = new \ReflectionProperty($propClass, 'normalProperty');
        $context = $this->propertyContext('unused', $prop);

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

        $this->translator()->processTranslation($context);
    }

    public function testProcessTranslationSkipsValidationForNonReflectionProperty(): void
    {
        // When property is not a ReflectionProperty, validation should not be called
        $context = $this->propertyContext('fallback');

        $this->attributeHelper()->expects($this->never())->method('validateProperty');

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn('result');
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($context);

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
            new LocaleVariantFinder($emStub),
            $this->logger(),
        );

        $context = new EntityTranslationContext($entity, 'en_US', 'de_DE');
        $result  = $translator->processTranslation($context);

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
        $prop    = new \ReflectionProperty($propClass, 'title');
        $context = $this->propertyContext('original', $prop);
        $context->setCopySource(false);

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(false);
        $this->attributeHelper()->method('isEmbedded')->willReturn(false);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->never())->method('translate');
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($context);

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
        $prop    = new \ReflectionProperty($propClass, 'shared');
        $context = $this->propertyContext('shared-value', $prop);
        $context->setCopySource(false);

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(true);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->once())->method('translate')->with(
            self::callback(static fn (TranslationContext $c): bool => $c->isShared()),
        )->willReturn('shared-value');
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($context);

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
        $prop    = new \ReflectionProperty($propClass, 'title');
        $context = $this->propertyContext('original', $prop);
        $context->setCopySource(false);

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

        $result = $this->translator()->processTranslation($context);

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
        $prop    = new \ReflectionProperty($propClass, 'created');
        $context = $this->propertyContext($propClass->created, $prop);
        $context->setCopySource(false);

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(false);
        $this->attributeHelper()->method('isEmbedded')->willReturn(false);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->once())->method('translate')->willReturn($propClass->created);
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($context);

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
        $prop    = new \ReflectionProperty($propClass, 'address');
        $context = $this->propertyContext($propClass->address, $prop);
        $context->setCopySource(false);

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmbedded')->willReturn(true);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(false);

        $embeddedResult = new \stdClass();
        $handler        = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->once())->method('translate')->willReturn($embeddedResult);
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($context);

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
        $prop    = new \ReflectionProperty($propClass, 'address');
        $context = $this->propertyContext($propClass->address, $prop);
        $context->setCopySource(false);

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

        $result = $this->translator()->processTranslation($context);

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
        $prop    = new \ReflectionProperty($propClass, 'body');
        $context = $this->propertyContext('some text', $prop);
        $context->setCopySource(true);

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(true);
        $this->attributeHelper()->method('isNullable')->willReturn(true);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->once())->method('translate')->with(
            self::callback(static fn (TranslationContext $c): bool => $c->isEmpty()),
        )->willReturn(null);
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($context);

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

        $context = new EntityTranslationContext($entity, 'en_US', 'de_DE');
        $result  = $this->translator()->processTranslation($context);

        // The entity-level attribute should override the global copySource (false)
        // and set the context's copySource to true
        self::assertTrue($context->getCopySource());
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

        $context = new EntityTranslationContext($entity, 'en_US', 'de_DE');
        $result  = $this->translator()->processTranslation($context);

        // Global copySource is false (from test setup)
        self::assertFalse($context->getCopySource());
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

        $context = new EntityTranslationContext($entity, 'en_US', 'de_DE');
        $result  = $this->translator()->processTranslation($context);

        // Global copySource is false
        self::assertFalse($context->getCopySource());
    }

    /**
     * #16 negative proof: a lazily-loaded association can arrive as a proxy
     * subclass generated one level below the real entity -- and PHP never
     * inherits attributes into a generated subclass, so #[Translatable] declared
     * on EmptySeeded is invisible on the proxy's own class. The old
     * `new \ReflectionClass($entity)` reflected that subclass directly, found no
     * attribute, and silently fell back to the global copy_source (true here) --
     * translating a copySource:false entity as though it were true. Uses a real
     * AttributeHelper (not the mock every other test in this file uses) so the
     * class actually being reflected is what decides the outcome.
     *
     * @throws \ReflectionException
     */
    public function testResolveCopySourceUnwrapsAProxyToSeeTheRealClasssAttribute(): void
    {
        $proxy = new class extends EmptySeeded implements Proxy {
            public function __load(): void
            {
            }

            public function __isInitialized(): bool
            {
                return true;
            }
        };
        $proxy->setLocale('en_US');

        $translator = new EntityTranslator(
            'en_US',
            ['de_DE', 'en_US', 'it_IT'],
            true, // global copy_source -- must lose to EmptySeeded's own copySource: false
            $this->eventDispatcher(),
            new AttributeHelper(),
            new TypeDefaultResolver(),
            $this->entityManager(),
            $this->cache(),
            $this->localeVariantFinder(),
        );

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn($proxy);
        $translator->addTranslationHandler($handler);

        $context = new EntityTranslationContext($proxy, 'en_US', 'de_DE');
        $translator->processTranslation($context);

        self::assertFalse($context->getCopySource(), "EmptySeeded's #[Translatable(copySource: false)] must survive the proxy, not fall back to the global copy_source: true");
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
        $prop    = new \ReflectionProperty($propClass, 'created');
        $context = $this->propertyContext($propClass->created, $prop);
        $context->setCopySource(false);

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

        $result = $this->translator()->processTranslation($context);

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
        $prop    = new \ReflectionProperty($propClass, 'title');
        $context = $this->propertyContext('original', $prop);
        $context->setCopySource(false);

        $this->attributeHelper()->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper()->method('isEmptyOnTranslate')->willReturn(false);
        $this->attributeHelper()->method('isEmbedded')->willReturn(false);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $this->translator()->addTranslationHandler($handler);

        $result = $this->translator()->processTranslation($context);

        self::assertNull($result);
    }

    public function testTranslateAndPersistCallsPersist(): void
    {
        [$translator, $em] = $this->createTranslatorWithMockEntityManager();

        $entity = new Scalar();
        $entity->setLocale('en_US');

        $translation = new Scalar();
        $translation->setTuuid($entity->getTuuid());
        $translation->setLocale('de_DE');

        // Pre-populate cache so translate() returns immediately
        $this->cache()->set($entity->getTuuid()->getValue(), 'de_DE', $translation);

        $em->expects($this->once())->method('persist')->with($translation);

        $result = $translator->translateAndPersist($entity, 'de_DE');
        self::assertSame($translation, $result);
    }

    public function testGetOrTranslateReturnsExistingWithoutPersisting(): void
    {
        [$translator, $em] = $this->createTranslatorWithMockEntityManager();

        $entity = new Scalar();
        $entity->setLocale('en_US');

        $translation = new Scalar();
        $translation->setTuuid($entity->getTuuid());
        $translation->setLocale('de_DE');

        $this->cache()->set($entity->getTuuid()->getValue(), 'de_DE', $translation);

        // Already managed → should not persist
        $em->method('contains')->with($translation)->willReturn(true);
        $em->expects($this->never())->method('persist');

        $result = $translator->getOrTranslate($entity, 'de_DE');
        self::assertSame($translation, $result);
    }

    public function testGetOrTranslatePersistsNewTranslation(): void
    {
        [$translator, $em] = $this->createTranslatorWithMockEntityManager();

        $entity = new Scalar();
        $entity->setLocale('en_US');

        $translation = new Scalar();
        $translation->setTuuid($entity->getTuuid());
        $translation->setLocale('de_DE');

        $this->cache()->set($entity->getTuuid()->getValue(), 'de_DE', $translation);

        // Not managed → should persist
        $em->method('contains')->with($translation)->willReturn(false);
        $em->expects($this->once())->method('persist')->with($translation);

        $result = $translator->getOrTranslate($entity, 'de_DE');
        self::assertSame($translation, $result);
    }

    /**
     * @return array{EntityTranslator, MockObject&EntityManagerInterface}
     */
    private function createTranslatorWithMockEntityManager(): array
    {
        $queryStub = static::createStub(Query::class);
        $queryStub->method('getResult')->willReturn([]);

        $qbStub = static::createStub(QueryBuilder::class);
        $qbStub->method('select')->willReturnSelf();
        $qbStub->method('from')->willReturnSelf();
        $qbStub->method('where')->willReturnSelf();
        $qbStub->method('andWhere')->willReturnSelf();
        $qbStub->method('setParameter')->willReturnSelf();
        $qbStub->method('getQuery')->willReturn($queryStub);

        /** @var MockObject&EntityManagerInterface $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qbStub);

        $translator = new EntityTranslator(
            'en_US',
            ['de_DE', 'en_US', 'it_IT'],
            false,
            $this->eventDispatcher(),
            $this->attributeHelper(),
            new TypeDefaultResolver(),
            $em,
            $this->cache(),
            new LocaleVariantFinder($em),
        );

        return [$translator, $em];
    }

    private function handlerSupporting(
        PropertyTranslationContext $expectedContext,
        mixed $return,
        callable|null $assert = null,
    ): TranslationHandlerInterface {
        $handler = $this->createMock(TranslationHandlerInterface::class);

        $matches = static fn (TranslationContext $context): bool => $context->getSubject() === $expectedContext->getSubject()
            && $context->getSourceLocale()                                                 === $expectedContext->getSourceLocale()
            && $context->getTargetLocale()                                                 === $expectedContext->getTargetLocale()
            && $context->getProperty()                                                     === $expectedContext->getProperty()
            && $context->getTranslatedParent()                                             === $expectedContext->getTranslatedParent();

        $handler->method('supports')->with(self::callback($matches))->willReturn(true);
        $handler->method('translate')->with(self::callback($matches))->willReturn($return);

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

        return $handler;
    }
}
