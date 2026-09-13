<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\ValueObject\SharedValueChange;
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

    public function testChangesCarryTheValuesBehindEveryChangedPath(): void
    {
        $changes = [
            new SharedValueChange('price', false, 10, 12),
            new SharedValueChange('address.street', false, 'old', 'new'),
        ];
        $report = new SharedValueSyncReport(['price', 'address.street'], [], [], $changes);

        self::assertSame($changes, $report->changes());
        self::assertSame(['price', 'address.street'], array_map(static fn (SharedValueChange $change): string => $change->path, $report->changes()));
    }

    public function testChangesDefaultToNone(): void
    {
        self::assertSame([], new SharedValueSyncReport([], [])->changes());
    }

    public function testReadonlyDriftAloneIsNotAChange(): void
    {
        $report = new SharedValueSyncReport([], ['sku']);

        self::assertFalse($report->hasChanges());
        self::assertSame(['sku'], $report->readonlyDrift());
    }
}
