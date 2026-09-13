<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Tmi\TranslationBundle\Command\RunMode;

#[CoversClass(RunMode::class)]
final class RunModeTest extends TestCase
{
    public function testWriteIsTheDefault(): void
    {
        $mode = RunMode::fromInput(self::input([]));

        self::assertSame(RunMode::Write, $mode);
        self::assertTrue($mode->writes());
        self::assertFalse($mode->isCheck());
        self::assertSame('', $mode->titleSuffix());
    }

    public function testDryRunWritesNothing(): void
    {
        $mode = RunMode::fromInput(self::input(['--dry-run' => true]));

        self::assertSame(RunMode::DryRun, $mode);
        self::assertFalse($mode->writes());
        self::assertFalse($mode->isCheck());
        self::assertSame(' (dry run)', $mode->titleSuffix());
    }

    /** --check implies --dry-run: it wins even when both are passed. */
    public function testCheckImpliesDryRun(): void
    {
        $mode = RunMode::fromInput(self::input(['--check' => true, '--dry-run' => true]));

        self::assertSame(RunMode::Check, $mode);
        self::assertFalse($mode->writes());
        self::assertTrue($mode->isCheck());
        self::assertSame(' (check)', $mode->titleSuffix());
    }

    /**
     * @param array<string, bool> $options
     */
    private static function input(array $options): ArrayInput
    {
        return new ArrayInput($options, new InputDefinition([
            new InputOption('dry-run', null, InputOption::VALUE_NONE),
            new InputOption('check', null, InputOption::VALUE_NONE),
        ]));
    }
}
