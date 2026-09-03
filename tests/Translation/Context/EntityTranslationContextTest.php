<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Context;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Context\EntityTranslationContext;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(EntityTranslationContext::class)]
final class EntityTranslationContextTest extends TestCase
{
    public function testConstructorAndGettersWork(): void
    {
        $entity = $this->createMock(TranslatableInterface::class);
        $source = 'en_US';
        $target = 'de_DE';

        $context = new EntityTranslationContext($entity, $source, $target);

        self::assertSame($entity, $context->getEntity());
        self::assertSame($entity, $context->getSubject());
        self::assertSame($source, $context->getSourceLocale());
        self::assertSame($target, $context->getTargetLocale());
        self::assertNull($context->getTranslatedParent());
        self::assertNull($context->getProperty());
        self::assertNull($context->getCopySource());
        self::assertFalse($context->isShared());
        self::assertFalse($context->isEmpty());
    }

    public function testNullableLocalesAllowed(): void
    {
        $entity  = $this->createMock(TranslatableInterface::class);
        $context = new EntityTranslationContext($entity);

        self::assertNull($context->getSourceLocale());
        self::assertNull($context->getTargetLocale());

        $context->setSourceLocale('en_US')->setTargetLocale('de_DE');

        self::assertSame('en_US', $context->getSourceLocale());
        self::assertSame('de_DE', $context->getTargetLocale());
    }

    public function testAssociationConstructorArgumentsCarryPropertyAndParent(): void
    {
        $entity = $this->createMock(TranslatableInterface::class);
        $parent = new \stdClass();
        $dummy  = new class {
            public int $prop = 42;
        };
        $property = new \ReflectionProperty($dummy::class, 'prop');

        $context = new EntityTranslationContext($entity, 'en_US', 'de_DE');
        $context->setProperty($property)->setTranslatedParent($parent);

        self::assertSame($property, $context->getProperty());
        self::assertSame($parent, $context->getTranslatedParent());
    }

    /**
     * @throws \ReflectionException
     */
    public function testFluentSettersAndMutability(): void
    {
        $entity  = $this->createMock(TranslatableInterface::class);
        $context = new EntityTranslationContext($entity);

        $context
            ->setCopySource(true)
            ->setShared(true)
            ->setEmpty(true);

        self::assertTrue($context->getCopySource());
        self::assertTrue($context->isShared());
        self::assertTrue($context->isEmpty());
    }

    public function testSetSubjectReplacesEntity(): void
    {
        $entity     = $this->createMock(TranslatableInterface::class);
        $translated = $this->createMock(TranslatableInterface::class);
        $context    = new EntityTranslationContext($entity, 'en_US', 'de_DE');

        $result = $context->setSubject($translated);

        self::assertSame($context, $result);
        self::assertSame($translated, $context->getEntity());
        self::assertSame($translated, $context->getSubject());
    }

    public function testCopySourceDefaultsToNull(): void
    {
        $context = new EntityTranslationContext($this->createMock(TranslatableInterface::class));
        self::assertNull($context->getCopySource());
    }

    public function testCopySourceSetterReturnsSelf(): void
    {
        $context = new EntityTranslationContext($this->createMock(TranslatableInterface::class));
        $result  = $context->setCopySource(true);
        self::assertSame($context, $result);
    }
}
