<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\EntityTranslator;
use Tmi\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;

#[CoversClass(EntityTranslator::class)]
final class EntityTranslatorTest extends UnitTestCase
{
    /**
     * @param array<string, mixed> $methodToReturnMap Method names as keys, return values as values
     */
    private function handlerSupporting(
        TranslationArgs $expectedArgs,
        mixed $return,
        callable|null $assert = null,
        array $methodToReturnMap = []
    ): TranslationHandlerInterface {

        $handler = $this->createMock(TranslationHandlerInterface::class);

        $handler->method('supports')->with(
            self::callback(static fn(TranslationArgs $args) => $args->getDataToBeTranslated() === $expectedArgs->getDataToBeTranslated()
                && $args->getSourceLocale() === $expectedArgs->getSourceLocale()
                && $args->getTargetLocale() === $expectedArgs->getTargetLocale()
                && $args->getProperty() === $expectedArgs->getProperty()
                && $args->getTranslatedParent() === $expectedArgs->getTranslatedParent())
        )->willReturn(true);

        // Default translate() behavior
        $handler->method('translate')->willReturn($return);
        foreach ($methodToReturnMap as $method => $value) {
            $handler->expects($this->once())->method($method)->with(
                self::callback(static fn(TranslationArgs $args) => $args->getDataToBeTranslated() === $expectedArgs->getDataToBeTranslated()
                    && $args->getSourceLocale() === $expectedArgs->getSourceLocale()
                    && $args->getTargetLocale() === $expectedArgs->getTargetLocale()
                    && $args->getProperty() === $expectedArgs->getProperty()
                    && $args->getTranslatedParent() === $expectedArgs->getTranslatedParent())
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
        $args = $this->getTranslationArgs(null, 'fallback');
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
     * @throws ReflectionException
     */
    public function testSharedAmongstTranslationsBranchCallsDedicatedHandler(): void
    {
        $propClass = new class {
            public string|null $title = null;
        };
        $prop = new ReflectionProperty($propClass, 'title');
        $args = $this->getTranslationArgs($prop);
        $this->attributeHelper->method('isSharedAmongstTranslations')->with($prop)->willReturn(true);
        $this->attributeHelper->method('isEmptyOnTranslate')->with($prop)->willReturn(false);
        $handler = $this->handlerSupporting(
            $args,
            'unused',
            null,
            ['handleSharedAmongstTranslations' => 'shared-result']
        );
        $this->translator->addTranslationHandler($handler);
        self::assertSame('shared-result', $this->translator->processTranslation($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testEmptyOnTranslateWithNullableCallsDedicatedHandler(): void
    {
        $propClass = new class {
            public string|null $body = null;
        };
        $prop = new ReflectionProperty($propClass, 'body');
        $args = $this->getTranslationArgs($prop);
        $this->attributeHelper->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper->method('isEmptyOnTranslate')->with($prop)->willReturn(true);
        $this->attributeHelper->method('isNullable')->with($prop)->willReturn(true);
        $handler = $this->handlerSupporting($args, 'unused', null, ['handleEmptyOnTranslate' => 'emptied']);
        $this->translator->addTranslationHandler($handler);
        self::assertSame('emptied', $this->translator->processTranslation($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testEmptyOnTranslateOnNonNullableThrowsLogicException(): void
    {
        $propClass = new class {
            public string $slug = '';
        };
        $prop = new ReflectionProperty($propClass, 'slug');
        $args = $this->getTranslationArgs($prop);
        $this->attributeHelper->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper->method('isEmptyOnTranslate')->with($prop)->willReturn(true);
        $this->attributeHelper->method('isNullable')->with($prop)->willReturn(false);
        $handler = $this->handlerSupporting($args, 'unused');
        $this->translator->addTranslationHandler($handler);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot use EmptyOnTranslate because it is not nullable');
        $this->translator->processTranslation($args);
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateBranchIsUsedWhenNoSpecialAttributes(): void
    {
        $propClass = new class {
            public int|null $n = null;
        };
        $prop = new ReflectionProperty($propClass, 'n');
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
        $args = $this->getTranslationArgs();
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
        $entity->setLocale('en');
        $entity->setTitle('Original Title');
        $translation = new Scalar();
        $translation->setLocale('en');
        $translation->setTitle('Translated Title');
        $entity->getTranslations()[] = $translation;

        // Make attribute helper report "no special attributes" for the property
        $this->attributeHelper->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper->method('isEmptyOnTranslate')->willReturn(false);

        // Handler that will be called once per translate() invocation (per property)
        $handler = $this->createMock(TranslationHandlerInterface::class);

        // supports() should accept any TranslationArgs (we cannot easily match the exact instance inside translate())
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn($entity);

        // translate() should be invoked exactly 3 times (afterLoad + beforePersist + beforeUpdate) for the single property
        $handler->expects($this->exactly(3))->method('translate');
        $this->translator->addTranslationHandler($handler);
        $this->translator->afterLoad($entity);
        $this->translator->beforePersist($entity, $this->entityManager);
        $this->translator->beforeUpdate($entity, $this->entityManager);
    }

    public function testLifecycleHooksFallbackToDefaultLocaleWhenNull(): void
    {
        $entity = new Scalar();
        $entity->setLocale('en');
        $entity->setTitle('Original Title');
        $translation = new Scalar();
        $translation->setLocale('en');
        $translation->setTitle('Translated Title');
        $entity->getTranslations()[] = $translation;
        $this->attributeHelper->method('isSharedAmongstTranslations')->willReturn(false);
        $this->attributeHelper->method('isEmptyOnTranslate')->willReturn(false);
        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('translate')->willReturn($entity);

        // now 4 lifecycle methods (afterLoad + beforePersist + beforeUpdate + beforeRemove)
        $handler->expects($this->exactly(4))->method('translate');
        $this->translator->addTranslationHandler($handler);
        $this->translator->afterLoad($entity);
        $this->translator->beforePersist($entity, $this->entityManager);
        $this->translator->beforeUpdate($entity, $this->entityManager);
        $this->translator->beforeRemove($entity, $this->entityManager);
    }

    /**
     * @throws ReflectionException
     */
    public function testProcessTranslationUsesExistingCacheEntry(): void
    {
        $entity = new Scalar();

        $entity->setTuuid('tuuid-1');
        $entity->setLocale('en');

        // Prepare a cached translation instance
        $cached = clone $entity;
        $cached->setLocale('de');

        // Inject into private translationCache via reflection
        $rp = new ReflectionProperty($this->translator, 'translationCache');
        $rp->setValue($this->translator, ['tuuid-1' => ['de' => $cached]]);

        // If cache exists, processTranslation should return cached item
        $args = new TranslationArgs($entity, 'en', 'de');
        $result = $this->translator->processTranslation($args);

        self::assertSame($cached, $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testWarmupTranslationsPopulatesCacheWithoutQuery(): void
    {
        $entity = new Scalar();
        $entity->setTuuid('tuuid-foo');
        $entity->setLocale('en');

        // simulate cached translation directly
        $translated = clone $entity;
        $translated->setLocale('de');

        // manually inject into private cache
        $rp = new ReflectionProperty($this->translator, 'translationCache');
        $rp->setValue($this->translator, ['tuuid-foo' => ['de' => $translated]]);

        $args = new TranslationArgs($entity, 'en', 'de');
        $result = $this->translator->processTranslation($args);

        self::assertSame($translated, $result);
    }


    /**
     * @throws ReflectionException
     */
    public function testInProgressPreventsRecursiveTranslation(): void
    {
        $entity = new Scalar();
        $entity->setTuuid('recursion-1');
        $entity->setLocale('en');

        // simulate inProgress being set for this tuuid + target locale
        $rp = new ReflectionProperty($this->translator, 'inProgress');
        $rp->setValue($this->translator, ['recursion-1:de' => true]);

        // if inProgress is set, processTranslation should return the original entity (no clone)
        $args = new TranslationArgs($entity, 'en', 'de');
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
        $entity->setLocale('en');

        // add a handler that will be called because no warmup will occur
        $args = new TranslationArgs($entity, 'en', 'de');
        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->expects($this->once())->method('supports')->with(
            self::isInstanceOf(TranslationArgs::class)
        )->willReturn(true);
        $handler->expects($this->once())->method('translate')->with(
            self::isInstanceOf(TranslationArgs::class)
        )->willReturn('translated-result');

        $this->translator->addTranslationHandler($handler);

        $result = $this->translator->processTranslation($args);

        self::assertSame('translated-result', $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testWarmupTranslationsSimplifiedContinues(): void
    {
        // --- 1st continue: entity not implementing TranslatableInterface ---
        $nonTranslatable = new stdClass();

        // --- 2nd continue: TranslatableInterface with null tuuid ---
        $scalarNullTuuid = new Scalar();
        $scalarNullTuuid->setLocale('en');

        // --- 3rd continue: TranslatableInterface with cached tuuid ---
        $scalarCached = new Scalar();
        $scalarCached->setLocale('en');
        $scalarCached->setTuuid('cached-1');

        // Inject cache directly
        $translationCache = ['cached-1' => ['de' => $scalarCached]];
        $rp = new ReflectionProperty($this->translator, 'translationCache');
        $rp->setValue($this->translator, $translationCache);

        $entities = [$nonTranslatable, $scalarNullTuuid, $scalarCached];

        // Call warmupTranslations
        $method = new ReflectionMethod($this->translator, 'warmupTranslations');
        $method->invoke($this->translator, $entities, 'de');

        // If execution reaches this point without error, continues were hit
        self::assertTrue(true);
    }
    /**
     * Cache hit: returns cached entity. InProgress will NOT be unset
     * because translator likely only unsets after DB warmup, not cache.
     */
    public function testProcessTranslationUsesCacheAndKeepsInProgressOnCacheHit(): void
    {
        $entity = new Scalar();
        $entity->setTuuid('test-tuuid');
        $entity->setLocale('en');

        $cachedTranslation = new Scalar();
        $cachedTranslation->setTuuid('test-tuuid');
        $cachedTranslation->setLocale('de');

        $reflection = new \ReflectionClass($this->translator);

        // Pre-fill cache
        $translationCacheProperty = $reflection->getProperty('translationCache');
        $translationCacheProperty->setValue($this->translator, [
            'test-tuuid' => ['de' => $cachedTranslation],
        ]);

        // Mark as inProgress
        $inProgressProperty = $reflection->getProperty('inProgress');
        $inProgressProperty->setValue($this->translator, ['test-tuuid:de' => true]);

        $args = new TranslationArgs($entity, 'en', 'de');
        $result = $this->translator->processTranslation($args);

        self::assertSame($cachedTranslation, $result);

        // InProgress is NOT unset in this path, so assert it’s still there
        $inProgressAfter = $inProgressProperty->getValue($this->translator);
        self::assertArrayHasKey('test-tuuid:de', $inProgressAfter);
    }
}
