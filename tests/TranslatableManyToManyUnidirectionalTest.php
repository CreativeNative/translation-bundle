<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Uid\Uuid;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyUnidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToManyUnidirectionalParent;
use Tmi\TranslationBundle\Translation\Context\PropertyTranslationContext;
use Tmi\TranslationBundle\Translation\Handlers\UnidirectionalManyToManyHandler;
use Tmi\TranslationBundle\ValueObject\Tuuid;

final class TranslatableManyToManyUnidirectionalTest extends IntegrationTestCase
{
    private UnidirectionalManyToManyHandler $handler;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();
        $this->handler = new UnidirectionalManyToManyHandler(
            $this->attributeHelper(),
            $this->translator(),
            $this->entityManager(),
        );
    }

    /**
     * @throws \ReflectionException
     */
    public function testSupportsReturnsTrueForCollectionWithManyToMany(): void
    {
        $parent = new TranslatableManyToManyUnidirectionalParent();
        $prop   = new \ReflectionProperty($parent::class, 'simpleChildren');

        // The data of a ManyToMany property is the collection, not the owning entity
        $context = new PropertyTranslationContext($parent->getSimpleChildren(), 'en_US', 'de_DE')
            ->setProperty($prop)
            ->setTranslatedParent($parent);

        self::assertTrue($this->handler->supports($context));
    }

    /**
     * Integration test: translate the parent and ensure the handler replaces the parent's simpleChildren.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \ReflectionException
     */
    public function testTranslateAddsItemsToCollection(): void
    {
        $tuuid1 = new Tuuid(Uuid::v4()->toRfc4122());
        $tuuid2 = new Tuuid(Uuid::v4()->toRfc4122());

        // Create children and set source locale explicitly
        $child1 = new TranslatableManyToManyUnidirectionalChild()
            ->setLocale('en_US')
            ->setTuuid($tuuid1);
        $child2 = new TranslatableManyToManyUnidirectionalChild()
            ->setLocale('en_US')
            ->setTuuid($tuuid2);

        $this->entityManager()->persist($child1);
        $this->entityManager()->persist($child2);

        // Create parent and attach simple children (unidirectional owning side)
        $parent = new TranslatableManyToManyUnidirectionalParent();
        $parent->addSimpleChild($child1);
        $parent->addSimpleChild($child2);

        $this->entityManager()->persist($parent);
        $this->entityManager()->flush();

        // Translate the parent entity (this will create translated children as needed)
        $parentTranslation = $this->translator()->translate($parent, 'de_DE');
        $this->entityManager()->persist($parentTranslation);
        $this->entityManager()->flush();

        // Reload the original parent if you need it; but for the handler we MUST pass the translated parent:
        $parent = $this->entityManager()->find(
            TranslatableManyToManyUnidirectionalParent::class,
            $parent->getId(),
        );
        self::assertNotNull($parent);

        // Get the ORIGINAL parent's collection as data-to-be-translated
        $children = $parent->getSimpleChildren();
        self::assertGreaterThan(0, $children->count());

        // IMPORTANT: the handler works on the translated parent – pass $parentTranslation here
        $property = new \ReflectionProperty($parentTranslation::class, 'simpleChildren');

        // Build the context: translate from 'en' -> 'de_DE', provide translated parent (the translated instance)
        $context = new PropertyTranslationContext($children, 'en_US', 'de_DE')
            ->setProperty($property)
            ->setTranslatedParent($parentTranslation);

        // Call the handler and assert results
        $result = $this->handler->translate($context);

        self::assertCount(2, $result, 'Translated collection should contain 2 items');

        // The source parent keeps its own children -- the handler builds a new collection
        // rather than clearing the one it shares with the source entity.
        self::assertCount(2, $parent->getSimpleChildren());

        foreach ($result as $item) {
            self::assertInstanceOf(TranslatableManyToManyUnidirectionalChild::class, $item);
            self::assertSame('de_DE', $item->getLocale(), 'Each translated child should have target locale "de"');
        }
    }

    public function testEmptyChildrenFieldReturnsEmptyCollection(): void
    {
        $parent   = new TranslatableManyToManyUnidirectionalParent();
        $children = $parent->getEmptyChildren();

        $context = new PropertyTranslationContext($children, 'en_US', 'de_DE');
        $context->setTranslatedParent($parent);
        $context->setEmpty(true);

        $result = $this->handler->translate($context);

        self::assertCount(0, $result);
    }
}
