<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\ManyToMany;
use ErrorException;
use ReflectionException;
use ReflectionProperty;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\NonTranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyBidirectionalParent;
use Tmi\TranslationBundle\Test\Translation\UnitTestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\BidirectionalManyToManyHandler;

/**
 * @covers \Tmi\TranslationBundle\Translation\Handlers\BidirectionalManyToManyHandler
 */
final class BidirectionalManyToManyHandlerTest extends UnitTestCase
{
    private BidirectionalManyToManyHandler $handler;

    public function setUp(): void
    {
        parent::setUp();
        $this->handler = new BidirectionalManyToManyHandler($this->attributeHelper);
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsFalseWhenEntityIsNotTranslatable(): void
    {
        $entity = new NonTranslatableManyToOneBidirectionalChild();
        $prop = new ReflectionProperty($entity, 'title');

        $args = new TranslationArgs($entity, 'en_US', 'it_IT')
            ->setProperty($prop);

        self::assertFalse($this->handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsFalseWhenNoPropertyPresent(): void
    {
        $entity = new TranslatableManyToManyBidirectionalParent();
        $entity->setTuuid('tuuid1')->setLocale('en');

        $prop = new ReflectionProperty($entity, 'title');

        $this->attributeHelper->method('isManyToMany')->with($prop)->willReturn(false);

        $args = new TranslationArgs($entity, 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($entity);

        self::assertFalse($this->handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsFalseWhenNoManyToManyAttributesPresent(): void
    {
        $entity = new TranslatableManyToManyBidirectionalParent();
        $entity->setTuuid('tuuid1')->setLocale('en');

        $prop = new ReflectionProperty($entity, 'title');

        $this->attributeHelper->method('isManyToMany')->with($prop)->willReturn(true);

        $args = new TranslationArgs($entity, 'en', 'de')
            ->setProperty($prop)
            ->setTranslatedParent($entity);

        self::assertFalse($this->handler->supports($args));
    }

    /**
     * @throws ReflectionException
     */
    public function testSupportsReturnsTrueWhenManyToManyAttributeExists(): void
    {
        $entity = new TranslatableManyToManyBidirectionalParent();

        $entity->setTuuid('tuuid1')->setLocale('en');

        $prop = new ReflectionProperty($entity, 'simpleChildren');

        $this->attributeHelper->method('isManyToMany')->with($prop)->willReturn(true);

        $args = new TranslationArgs($entity, 'en_US', 'it_IT')
            ->setProperty($prop)
            ->setTranslatedParent($entity);

        self::assertTrue($this->handler->supports($args));
    }


    /**
     * @throws ReflectionException
     */
    public function testHandleSharedAmongstTranslationsThrowsErrorException(): void
    {
        $entity = new TranslatableManyToManyBidirectionalParent();
        $prop = new ReflectionProperty($entity, 'sharedChildren');

        $args = new TranslationArgs($entity, 'en_US', 'it_IT')
            ->setProperty($prop);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage(sprintf(
            '%s::%s is a ManyToMany relation',
            $entity::class,
            $prop->getName()
        ));

        $this->handler->handleSharedAmongstTranslations($args);
    }

    /**
     * @throws ReflectionException
     */
    public function testHandleEmptyOnTranslateReturnsEmptyCollection(): void
    {
        $entity = new TranslatableManyToManyBidirectionalParent();
        $prop = new ReflectionProperty($entity, 'emptyChildren');

        $args = new TranslationArgs($entity, 'en_US', 'it_IT')
            ->setProperty($prop);

        $result = $this->handler->handleEmptyOnTranslate($args);

        self::assertCount(0, $result);
    }

    public function testTranslateReturnsSameCollectionIfAlreadyCollection(): void
    {
        $collection = new ArrayCollection([1, 2, 3]);

        $args = new TranslationArgs($collection, 'en_US', 'it_IT');

        $result = $this->handler->translate($args);

        self::assertSame($collection, $result);
    }
}
