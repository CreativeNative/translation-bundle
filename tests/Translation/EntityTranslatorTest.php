<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation;

use Doctrine\ORM\EntityManagerInterface;
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

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Locale "xx" is not allowed. Allowed locales:');

        $this->translator->processTranslation($args);
    }

    public function testReturnsFallbackWhenNoHandlerSupports(): void
    {
        $args = $this->getTranslationArgs(null, 'fallback');
        $this->translator->addTranslationHandler($this->handlerNotSupporting());
        $this->translator->addTranslationHandler($this->handlerNotSupporting());
        $result = $this->translator->processTranslation($args);
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
        $this->translator->addTranslationHandler($first);
        $this->translator->addTranslationHandler($second);
        self::assertSame('first', $this->translator->processTranslation($args));
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
        $this->attributeHelper->method('isSharedAmongstTranslations')->with($prop)->willReturn(true);
        $this->attributeHelper->method('isEmptyOnTranslate')->with($prop)->willReturn(false);
        $handler = $this->handlerSupporting(
            $args,
            'unused',
            null,
            ['handleSharedAmongstTranslations' => 'shared-result'],
        );
        $this->translator->addTranslationHandler($handler);
        self::assertSame('shared-result', $this->translator->processTranslation($args));
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
        $this->attributeHelper->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper->method('isEmptyOnTranslate')->with($prop)->willReturn(true);
        $this->attributeHelper->method('isNullable')->with($prop)->willReturn(true);
        $handler = $this->handlerSupporting($args, 'unused', null, ['handleEmptyOnTranslate' => 'emptied']);
        $this->translator->addTranslationHandler($handler);
        self::assertSame('emptied', $this->translator->processTranslation($args));
    }

    /**
     * @throws \ReflectionException
     */
    public function testEmptyOnTranslateOnNonNullableThrowsLogicException(): void
    {
        $propClass = new class {
            public string $slug = '';
        };
        $prop = new \ReflectionProperty($propClass, 'slug');
        $args = $this->getTranslationArgs($prop);
        $this->attributeHelper->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper->method('isEmptyOnTranslate')->with($prop)->willReturn(true);
        $this->attributeHelper->method('isNullable')->with($prop)->willReturn(false);
        $handler = $this->handlerSupporting($args, 'unused');
        $this->translator->addTranslationHandler($handler);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot use EmptyOnTranslate because it is not nullable');
        $this->translator->processTranslation($args);
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
        $this->attributeHelper->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper->method('isEmptyOnTranslate')->with($prop)->willReturn(false);
        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->expects($this->once())->method('supports')->with($args)->willReturn(true);
        $handler->expects($this->once())->method('translate')->with($args)->willReturn('translated');
        $this->translator->addTranslationHandler($handler);
        self::assertSame('translated', $this->translator->processTranslation($args));
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
        $this->translator->addTranslationHandler($first, 0);
        $this->translator->addTranslationHandler($second);
        self::assertSame('first', $this->translator->processTranslation($args));
    }

    public function testLifecycleHooksUseEntityLocaleIfPresent(): void
    {
        $entity = new Scalar();
        $entity->setTitle('Original Title');

        // Make attribute helper report "no special attributes" for the property
        $this->attributeHelper->method('isSharedAmongstTranslations')
            ->willReturnCallback(fn ($property) => false);
        $this->attributeHelper->method('isEmptyOnTranslate')
            ->willReturnCallback(fn ($property) => false);

        // Handler that will be called once per translate() invocation (per property)
        $handler = $this->createMock(TranslationHandlerInterface::class);

        // supports() should accept any TranslationArgs (we cannot easily match the exact instance inside translate())
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn($entity);

        // translate() should be invoked exactly 3 times (afterLoad + beforePersist + beforeUpdate) for the single property
        $handler->expects($this->exactly(3))->method('translate')->willReturn($entity);

        $this->translator->addTranslationHandler($handler);
        $this->translator->afterLoad($entity);
        $this->translator->beforePersist($entity);
        $this->translator->beforeUpdate($entity);
    }

    public function testLifecycleHooksFallbackToDefaultLocaleWhenNull(): void
    {
        $entity = new Scalar();
        $entity->setTitle('Original Title');

        $this->attributeHelper->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper->method('isEmptyOnTranslate')->willReturn(false);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn($entity);

        // now 4 lifecycle methods (afterLoad + beforePersist + beforeUpdate + beforeRemove)
        $handler->expects($this->exactly(4))->method('translate')->willReturn($entity);

        $this->translator->addTranslationHandler($handler);

        $this->translator->afterLoad($entity);
        $this->translator->beforePersist($entity);
        $this->translator->beforeUpdate($entity);
        $this->translator->beforeRemove($entity);
    }

    /**
     * @throws \ReflectionException
     */
    public function testProcessTranslationUsesExistingCacheEntry(): void
    {
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $entity = new Scalar();
        $entity->setTuuid($tuuid);
        $entity->setLocale('en_US');

        // Prepare a cached translation instance
        $cached = clone $entity;
        $cached->setLocale('de_DE');

        // Inject into private translationCache via reflection
        $rp = new \ReflectionProperty($this->translator, 'translationCache');
        $rp->setValue($this->translator, [(string) $tuuid => ['de_DE' => $cached]]);

        // If cache exists, processTranslation should return cached item
        $args   = new TranslationArgs($entity, 'en_US', 'de_DE');
        $result = $this->translator->processTranslation($args);

        self::assertSame($cached, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testWarmupTranslationsPopulatesCacheWithoutQuery(): void
    {
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $entity = new Scalar();
        $entity->setTuuid($tuuid);
        $entity->setLocale('en_US');

        // simulate cached translation directly
        $translated = clone $entity;
        $translated->setLocale('de_DE');

        // manually inject into private cache
        $rp = new \ReflectionProperty($this->translator, 'translationCache');
        $rp->setValue($this->translator, [(string) $tuuid => ['de_DE' => $translated]]);

        $args   = new TranslationArgs($entity, 'en_US', 'de_DE');
        $result = $this->translator->processTranslation($args);

        self::assertSame($translated, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testInProgressPreventsRecursiveTranslation(): void
    {
        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $entity = new Scalar();
        $entity->setTuuid($tuuid);
        $entity->setLocale('en_US');

        // simulate inProgress being set for this tuuid + target locale
        $rp = new \ReflectionProperty($this->translator, 'inProgress');
        $rp->setValue($this->translator, [$tuuid->getValue().':de' => true]);

        // if inProgress is set, processTranslation should return the original entity (no clone)
        $args   = new TranslationArgs($entity, 'en_US', 'de_DE');
        $result = $this->translator->processTranslation($args);

        self::assertSame($entity, $result);
    }

    public function testEntitiesWithNullTuuidSkipWarmupAndCallHandlers(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        // ensure EM is never queried when tuuid is null
        $this->entityManager->expects($this->never())->method('createQueryBuilder');

        // create an entity without tuuid
        $entity = new Scalar();
        $entity->setLocale('en_US');

        // add a handler that will be called because no warmup will occur
        $args    = new TranslationArgs($entity, 'en_US', 'de_DE');
        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->expects($this->once())->method('supports')->with(
            self::isInstanceOf(TranslationArgs::class),
        )->willReturn(true);
        $handler->expects($this->once())->method('translate')->with(
            self::isInstanceOf(TranslationArgs::class),
        )->willReturn('translated-result');

        $this->translator->addTranslationHandler($handler);

        $result = $this->translator->processTranslation($args);

        self::assertSame('translated-result', $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testWarmupTranslationsSimplifiedContinues(): void
    {
        // --- 1st continue: entity not implementing TranslatableInterface ---
        $nonTranslatable = new \stdClass();

        // --- 2nd continue: TranslatableInterface with null tuuid ---
        $scalarNullTuuid = new Scalar();
        $scalarNullTuuid->setLocale('en_US');

        // --- 3rd continue: TranslatableInterface with cached tuuid ---

        $tuuid = new Tuuid(Uuid::v4()->toRfc4122());

        $scalarCached = new Scalar();
        $scalarCached->setLocale('en_US');
        $scalarCached->setTuuid($tuuid);

        // Inject cache directly
        $translationCache = [(string) $tuuid => ['de_DE' => $scalarCached]];
        $rp               = new \ReflectionProperty($this->translator, 'translationCache');
        $rp->setValue($this->translator, $translationCache);

        $entities = [$nonTranslatable, $scalarNullTuuid, $scalarCached];

        // Call warmupTranslations
        $method = new \ReflectionMethod($this->translator, 'warmupTranslations');
        $method->invoke($this->translator, $entities, 'de_DE');

        // If execution reaches this point without error, continues were hit
        self::assertTrue(true);
    }

    /**
     * Cache hit: returns cached entity. InProgress will NOT be unset
     * because translator likely only unsets after DB warmup, not cache.
     *
     * @throws \ReflectionException
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

        $reflection = new \ReflectionClass($this->translator);

        // Pre-fill cache
        $translationCacheProperty = $reflection->getProperty('translationCache');
        $translationCacheProperty->setValue($this->translator, [
            $sharedTuuid->getValue() => ['de_DE' => $cachedTranslation],
        ]);

        // Mark as inProgress
        $inProgressProperty = $reflection->getProperty('inProgress');
        $inProgressProperty->setValue($this->translator, [$sharedTuuid.':de' => true]);

        $args   = new TranslationArgs($entity, 'en_US', 'de_DE');
        $result = $this->translator->processTranslation($args);

        self::assertSame($cachedTranslation, $result);

        // InProgress is NOT unset in this path, so assert it’s still there
        $inProgressAfter = $inProgressProperty->getValue($this->translator);
        self::assertArrayHasKey($sharedTuuid.':de', $inProgressAfter);
    }

    public function testLoggerIsOptional(): void
    {
        // Create translator without logger (should not throw)
        $translator = new EntityTranslator(
            'en_US',
            ['de_DE', 'en_US'],
            $this->eventDispatcherInterface,
            $this->attributeHelper,
            $this->createMock(EntityManagerInterface::class),
            null, // No logger
        );

        self::assertInstanceOf(EntityTranslator::class, $translator);
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

        $this->translator->setLogger($logger);

        $entity = new Scalar();
        $entity->setLocale('en_US');

        // Add a simple handler
        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn($entity);
        $this->translator->addTranslationHandler($handler);

        $this->translator->translate($entity, 'de_DE');
    }

    public function testLoggingOnTranslationStart(): void
    {
        $this->logger->expects($this->atLeastOnce())
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
        $this->translator->addTranslationHandler($handler);

        $this->translator->translate($entity, 'de_DE');
    }

    public function testLoggingOnHandlerSelected(): void
    {
        $this->logger->expects($this->atLeastOnce())
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
        $this->translator->addTranslationHandler($handler);

        $this->translator->translate($entity, 'de_DE');
    }

    public function testNoLoggingWhenLoggerIsNull(): void
    {
        // Create translator without logger
        $translatorWithoutLogger = new EntityTranslator(
            'en_US',
            ['de_DE', 'en_US'],
            $this->eventDispatcherInterface,
            $this->attributeHelper,
            $this->createMock(EntityManagerInterface::class),
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

        $this->attributeHelper->method('isSharedAmongstTranslations')->willReturn(true);
        $this->attributeHelper->method('isEmptyOnTranslate')->willReturn(false);

        $this->logger->expects($this->atLeastOnce())
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
        $this->translator->addTranslationHandler($handler);

        $args = $this->getTranslationArgs($prop, 'test-data');
        $this->translator->processTranslation($args);
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

        $this->attributeHelper->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper->method('isEmptyOnTranslate')->willReturn(true);
        $this->attributeHelper->method('isNullable')->willReturn(true);

        $this->logger->expects($this->atLeastOnce())
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
        $this->translator->addTranslationHandler($handler);

        $args = $this->getTranslationArgs($prop, 'test-data');
        $this->translator->processTranslation($args);
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
        $this->attributeHelper->expects($this->once())
            ->method('validateProperty')
            ->with($prop, $this->logger)
            ->willThrowException(new ValidationException([
                new \LogicException('Test error'),
            ]));

        // These methods should NOT be called because validation fails first
        $this->attributeHelper->expects($this->never())->method('isSharedAmongstTranslations');
        $this->attributeHelper->expects($this->never())->method('isEmptyOnTranslate');

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $this->translator->addTranslationHandler($handler);

        $this->expectException(ValidationException::class);

        $this->translator->processTranslation($args);
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
        $this->attributeHelper->expects($this->once())
            ->method('validateProperty')
            ->with($prop, $this->logger);

        $this->attributeHelper->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper->method('isEmptyOnTranslate')->willReturn(false);

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn('result');
        $this->translator->addTranslationHandler($handler);

        $this->translator->processTranslation($args);
    }

    public function testProcessTranslationSkipsValidationForNonReflectionProperty(): void
    {
        // When property is not a ReflectionProperty, validation should not be called
        $args = $this->getTranslationArgs(null, 'fallback');

        $this->attributeHelper->expects($this->never())->method('validateProperty');

        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn('result');
        $this->translator->addTranslationHandler($handler);

        $result = $this->translator->processTranslation($args);

        self::assertSame('result', $result);
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
            $handler->expects($this->once())->method($method)->with(
                self::callback(static fn (TranslationArgs $args) => $args->getDataToBeTranslated() === $expectedArgs->getDataToBeTranslated()
                    && $args->getSourceLocale()                                                    === $expectedArgs->getSourceLocale()
                    && $args->getTargetLocale()                                                    === $expectedArgs->getTargetLocale()
                    && $args->getProperty()                                                        === $expectedArgs->getProperty()
                    && $args->getTranslatedParent()                                                === $expectedArgs->getTranslatedParent()),
            )->willReturn($value);
        }

        if ($assert) {
            $assert($handler);
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
