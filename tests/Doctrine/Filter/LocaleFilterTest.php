<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Test\IntegrationTestCase;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(LocaleFilter::class)]
final class LocaleFilterTest extends IntegrationTestCase
{
    private LocaleFilter $filter;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        // Enable the LocaleFilter through the real EntityManager
        $this->entityManager()->getConfiguration()->addFilter(
            'tmi_translation_locale_filter',
            LocaleFilter::class,
        );
        $filter = $this->entityManager()->getFilters()->enable('tmi_translation_locale_filter');
        self::assertInstanceOf(LocaleFilter::class, $filter);
        $this->filter = $filter;
    }

    public function testSetLocaleSetsParameter(): void
    {
        $this->filter->setLocale('en_US');

        $metadata   = $this->createMock(ClassMetadata::class);
        $reflection = $this->createMock(\ReflectionClass::class);
        $reflection->method('getInterfaceNames')->willReturn([]); // not translatable
        $metadata->method('getReflectionClass')->willReturn($reflection);

        $sql = $this->filter->addFilterConstraint($metadata, 't');
        self::assertSame('', $sql); // should return empty because entity is not translatable
    }

    public function testAddFilterConstraintReturnsSqlForTranslatable(): void
    {
        $this->filter->setLocale('en_US');

        $metadata   = $this->createMock(ClassMetadata::class);
        $reflection = $this->createMock(\ReflectionClass::class);
        $reflection->method('getInterfaceNames')->willReturn([TranslatableInterface::class]);
        $metadata->method('getReflectionClass')->willReturn($reflection);

        $sql = $this->filter->addFilterConstraint($metadata, 't');
        self::assertSame("t.locale = 'en_US'", $sql);
    }

    public function testAddFilterConstraintReturnsEmptyForNonTranslatable(): void
    {
        $this->filter->setLocale('en_US');

        $metadata   = $this->createMock(ClassMetadata::class);
        $reflection = $this->createMock(\ReflectionClass::class);
        $reflection->method('getInterfaceNames')->willReturn([]);
        $metadata->method('getReflectionClass')->willReturn($reflection);

        $sql = $this->filter->addFilterConstraint($metadata, 't');
        self::assertSame('', $sql);
    }

    public function testAddFilterConstraintReturnsEmptyIfLocaleNotSet(): void
    {
        $metadata   = $this->createMock(ClassMetadata::class);
        $reflection = $this->createMock(\ReflectionClass::class);
        $reflection->method('getInterfaceNames')->willReturn([TranslatableInterface::class]);
        $metadata->method('getReflectionClass')->willReturn($reflection);

        // Locale not set
        $sql = $this->filter->addFilterConstraint($metadata, 't');
        self::assertSame('', $sql);
    }
}
