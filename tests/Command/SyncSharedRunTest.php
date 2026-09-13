<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Command\SyncSharedRun;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\ValueObject\Tuuid;

#[CoversClass(SyncSharedRun::class)]
final class SyncSharedRunTest extends TestCase
{
    public function testUnwritableValuesLandInTheirOwnListAndInTheDriftTable(): void
    {
        $run   = new SyncSharedRun();
        $tuuid = Tuuid::generate();
        $row   = new Scalar()->setTuuid($tuuid)->setLocale('de_DE');

        $run->noteUnwritable($row, 'frozen', false);
        $run->noteUnwritable($row, 'listing', true);

        self::assertSame([\sprintf('%s::$frozen (tuuid %s, locale de_DE)', Scalar::class, $tuuid)], $run->readonlyDrift);
        self::assertSame([\sprintf('%s::$listing (tuuid %s, locale de_DE)', Scalar::class, $tuuid)], $run->rootDrift);
        self::assertTrue($run->hasUnwritable());
        self::assertSame(
            [['frozen', '1', '1', 'no'], ['listing', '1', '1', 'no']],
            $run->driftRows(),
        );
    }

    public function testDriftRowsCountTuuidsAndRowsSeparatelyAndSortByRows(): void
    {
        $run = new SyncSharedRun();
        $a   = Tuuid::generate();
        $b   = Tuuid::generate();

        $run->recordDrift('title', new Scalar()->setTuuid($a)->setLocale('de_DE'), false);
        $run->recordDrift('title', new Scalar()->setTuuid($a)->setLocale('it_IT'), false);
        $run->recordDrift('title', new Scalar()->setTuuid($b)->setLocale('de_DE'), false);
        $run->recordDrift('shared', new Scalar()->setTuuid($a)->setLocale('de_DE'), false);

        self::assertFalse($run->hasUnwritable());
        self::assertSame(
            [['title', '2', '3', 'yes'], ['shared', '1', '1', 'yes']],
            $run->driftRows(),
        );
    }

    public function testBeginClassResetsTheTableButKeepsTheRunWideLists(): void
    {
        $run = new SyncSharedRun();
        $row = new Scalar()->setTuuid(Tuuid::generate())->setLocale('de_DE');

        $run->noteUnwritable($row, 'frozen', false);
        $run->beginClass();

        self::assertSame([], $run->drift);
        self::assertCount(1, $run->readonlyDrift);
        self::assertSame([], $run->driftRows());
    }
}
