<?php
declare(strict_types=1);

namespace TMI\TranslationBundle\Test;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use ReflectionException;
use ReflectionProperty;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyUnidirectionalChild;
use TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyUnidirectionalParent;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\Handlers\UnidirectionalManyToManyHandler;

final class TranslatableManyToManyUnidirectionalTest extends TestCase
{
    private UnidirectionalManyToManyHandler $handler;

    public function setUp(): void
    {
        parent::setUp();

        $this->handler = new UnidirectionalManyToManyHandler(
            $this->attributeHelper,
            $this->translator,
            $this->entityManager
        );
    }

    public function testSupportsReturnsTrueForCollectionWithManyToMany(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();

        $prop = new ReflectionProperty($parent::class, 'simpleChildren');
        $prop->setAccessible(true);

        $args = new TranslationArgs(new ArrayCollection(), 'de', self::TARGET_LOCALE)
            ->setProperty($prop)
            ->setTranslatedParent($parent);

        self::assertTrue($this->handler->supports($args));
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     */
    public function testTranslateAddsItemsToCollection(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();
        $child1 = new TranslatableManyToManyUnidirectionalChild();
        $child2 = new TranslatableManyToManyUnidirectionalChild();

        $this->entityManager->persist($child1);
        $this->entityManager->persist($child2);

        $parent->addSimpleChild($child1);
        $parent->addSimpleChild($child2);

        $this->entityManager->persist($parent);
        $this->entityManager->flush();

        $parentTranslation = $this->translator->translate($parent, 'de');
        assert($parentTranslation instanceof TranslatableManyToManyUnidirectionalParent);

        $this->entityManager->persist($parentTranslation);
        $this->entityManager->flush();

        $children = $parent->getSimpleChildren();

        $args = new TranslationArgs($children, 'en', 'de')
            ->setTranslatedParent($parent);

        $result = $this->handler->translate($args);

        self::assertInstanceOf(ArrayCollection::class, $result);
//      ToDo: fix me
//      self::assertCount(2, $result);
        foreach ($result as $item) {
            self::assertInstanceOf(TranslatableInterface::class, $item);
            self::assertEquals('de', $item->getLocale());
        }
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws ReflectionException
     */
    public function testHandleSharedAmongstTranslationsFieldIsHandled(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();
        $child = new TranslatableManyToManyUnidirectionalChild();

        $this->entityManager->persist($child);

        $parent->addSharedChild($child);
        $this->entityManager->persist($parent);
        $this->entityManager->flush();

        $parentTranslation = $this->translator->translate($parent, 'de');
        assert($parentTranslation instanceof TranslatableManyToManyUnidirectionalParent);

        $this->entityManager->persist($parentTranslation);
        $this->entityManager->flush();

        $children = $parent->getSharedChildren();

        $args = new TranslationArgs($children, 'en', 'de')
            ->setTranslatedParent($parent);

        $result = $this->handler->handleSharedAmongstTranslations($args);

        self::assertInstanceOf(ArrayCollection::class, $result);
//      ToDo: fix me
//      self::assertCount(1, $result);
//      self::assertSame($child, $result->first());
    }

    public function testEmptyChildrenFieldReturnsEmptyCollection(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();

        $children = $parent->getEmptyChildren();

        $args = new TranslationArgs($children, 'en', 'de')
            ->setTranslatedParent($parent);

        self::assertInstanceOf(Collection::class, $this->handler->handleEmptyOnTranslate($args));
        self::assertCount(0, $this->handler->handleEmptyOnTranslate($args));

    }
}
