<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\EntityTranslator;
use Tmi\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;

#[CoversClass(EntityTranslator::class)]
final class EntityTranslatorTest extends TestCase
{
    private EventDispatcherInterface $eventDispatcherInterface;
    private AttributeHelper $attributeHelper;
    public function setUp(): void
    {
        $this->eventDispatcherInterface = $this->createMock(EventDispatcherInterface::class);
        $this->attributeHelper = $this->createMock(AttributeHelper::class);
    }

    // Create a real EntityTranslator instance (class is final so cannot be mocked)
    private function newTranslator(): EntityTranslator
    {

        return new EntityTranslator(
            'en',
            ['de', 'en', 'it'],
            $this->eventDispatcherInterface,
            $this->attributeHelper
        );
    }

    // Create a real TranslationArgs instance (class is final so cannot be mocked)
    private function newTranslationArgs(?ReflectionProperty $prop = null, mixed $fallback = null): TranslationArgs
    {
        $args = new TranslationArgs($fallback, 'en', 'de');
        if ($prop !== null) {
            $args->setProperty($prop);
        }

        return $args;
    }

    private function handlerSupporting(
        TranslationArgs $expectedArgs,
        mixed $return,
        ?callable $assert = null,
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
        $translator = $this->newTranslator();
        $args = $this->newTranslationArgs(null, 'fallback');
        $translator->addTranslationHandler($this->handlerNotSupporting());
        $translator->addTranslationHandler($this->handlerNotSupporting());
        $result = $translator->processTranslation($args);
        self::assertSame('fallback', $result);
    }

    public function testFirstSupportingHandlerWins(): void
    {
        $translator = $this->newTranslator();
        $args = $this->newTranslationArgs(null, 'fallback');
        $first = $this->createMock(TranslationHandlerInterface::class);
        $first->expects($this->once())->method('supports')->with($args)->willReturn(true);
        $first->expects($this->once())->method('translate')->with($args)->willReturn('first');
        $second = $this->createMock(TranslationHandlerInterface::class);
        $second->expects($this->never())->method('supports');

        // not reached
        $second->expects($this->never())->method('translate');
        $translator->addTranslationHandler($first);
        $translator->addTranslationHandler($second);
        self::assertSame('first', $translator->processTranslation($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testSharedAmongstTranslationsBranchCallsDedicatedHandler(): void
    {
        $translator = $this->newTranslator();
        $propClass = new class {
            public ?string $title = null;
        };
        $prop = new ReflectionProperty($propClass, 'title');
        $args = $this->newTranslationArgs($prop);
        $this->attributeHelper->method('isSharedAmongstTranslations')->with($prop)->willReturn(true);
        $this->attributeHelper->method('isEmptyOnTranslate')->with($prop)->willReturn(false);
        $handler = $this->handlerSupporting(
            $args,
            'unused',
            null,
            ['handleSharedAmongstTranslations' => 'shared-result']
        );
        $translator->addTranslationHandler($handler);
        self::assertSame('shared-result', $translator->processTranslation($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testEmptyOnTranslateWithNullableCallsDedicatedHandler(): void
    {
        $translator = $this->newTranslator();
        $propClass = new class {
            public ?string $body = null;
        };
        $prop = new ReflectionProperty($propClass, 'body');
        $args = $this->newTranslationArgs($prop);
        $this->attributeHelper->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper->method('isEmptyOnTranslate')->with($prop)->willReturn(true);
        $this->attributeHelper->method('isNullable')->with($prop)->willReturn(true);
        $handler = $this->handlerSupporting($args, 'unused', null, ['handleEmptyOnTranslate' => 'emptied']);
        $translator->addTranslationHandler($handler);
        self::assertSame('emptied', $translator->processTranslation($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testEmptyOnTranslateOnNonNullableThrowsLogicException(): void
    {
        $translator = $this->newTranslator();
        $propClass = new class {
            public string $slug = '';
        };
        $prop = new ReflectionProperty($propClass, 'slug');
        $args = $this->newTranslationArgs($prop);
        $this->attributeHelper->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper->method('isEmptyOnTranslate')->with($prop)->willReturn(true);
        $this->attributeHelper->method('isNullable')->with($prop)->willReturn(false);
        $handler = $this->handlerSupporting($args, 'unused');
        $translator->addTranslationHandler($handler);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot use EmptyOnTranslate because it is not nullable');
        $translator->processTranslation($args);
    }

    /**
     * @throws ReflectionException
     */
    public function testTranslateBranchIsUsedWhenNoSpecialAttributes(): void
    {
        $translator = $this->newTranslator();
        $propClass = new class {
            public ?int $n = null;
        };
        $prop = new ReflectionProperty($propClass, 'n');
        $args = $this->newTranslationArgs($prop);
        $this->attributeHelper->method('isSharedAmongstTranslations')->with($prop)->willReturn(false);
        $this->attributeHelper->method('isEmptyOnTranslate')->with($prop)->willReturn(false);
        $handler = $this->createMock(TranslationHandlerInterface::class);
        $handler->expects($this->once())->method('supports')->with($args)->willReturn(true);
        $handler->expects($this->once())->method('translate')->with($args)->willReturn('translated');
        $translator->addTranslationHandler($handler);
        self::assertSame('translated', $translator->processTranslation($args));
    }

    public function testAddTranslationHandlerOrderWithExplicitKey(): void
    {
        $translator = $this->newTranslator();
        $args = $this->newTranslationArgs();
        $first = $this->createMock(TranslationHandlerInterface::class);
        $first->expects($this->once())->method('supports')->with($args)->willReturn(true);
        $first->expects($this->once())->method('translate')->with($args)->willReturn('first');
        $second = $this->createMock(TranslationHandlerInterface::class);
        $second->expects($this->never())->method('supports');

        // Insert with explicit key FIRST; then append another one.
        $translator->addTranslationHandler($first, 0);
        $translator->addTranslationHandler($second);
        self::assertSame('first', $translator->processTranslation($args));
    }

    public function testLifecycleHooksUseEntityLocaleIfPresent(): void
    {
        // Use a real EntityTranslator (cannot mock final), register a handler that will be invoked
        $translator = $this->newTranslator();
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
        $translator->addTranslationHandler($handler);
        $em = $this->createMock(EntityManagerInterface::class);
        $translator->afterLoad($entity);
        $translator->beforePersist($entity, $em);
        $translator->beforeUpdate($entity, $em);
    }

    public function testLifecycleHooksFallbackToDefaultLocaleWhenNull(): void
    {
        $translator = $this->newTranslator();
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
        $translator->addTranslationHandler($handler);
        $em = $this->createMock(EntityManagerInterface::class);
        $translator->afterLoad($entity);
        $translator->beforePersist($entity, $em);
        $translator->beforeUpdate($entity, $em);
        $translator->beforeRemove($entity, $em);
    }
}
