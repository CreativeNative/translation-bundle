<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\ValueObject\SharedValueSyncReport;

#[CoversClass(SharedValueSyncReport::class)]
final class SharedValueSyncReportTest extends TestCase
{
    public function testExposesChangedAndReadonlyPaths(): void
    {
        $report = new SharedValueSyncReport(['price', 'address.street'], ['sku']);

        self::assertSame(['price', 'address.street'], $report->changed());
        self::assertSame(['sku'], $report->readonlyDrift());
        self::assertTrue($report->hasChanges());
    }

    public function testReadonlyDriftAloneIsNotAChange(): void
    {
        $report = new SharedValueSyncReport([], ['sku']);

        self::assertFalse($report->hasChanges());
        self::assertSame(['sku'], $report->readonlyDrift());
    }
}
