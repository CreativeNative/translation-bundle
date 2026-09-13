<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Command;

use Symfony\Component\Console\Input\InputInterface;

/**
 * The three ways a maintenance command can be asked to run, resolved once from
 * the `--check` / `--dry-run` options every batch command declares.
 *
 * `--check` implies `--dry-run`: both write nothing, and only Check turns the
 * findings into a non-zero exit code for a CI gate.
 */
enum RunMode
{
    case Write;
    case DryRun;
    case Check;

    public static function fromInput(InputInterface $input): self
    {
        if (true === $input->getOption('check')) {
            return self::Check;
        }

        return true === $input->getOption('dry-run') ? self::DryRun : self::Write;
    }

    public function writes(): bool
    {
        return self::Write === $this;
    }

    public function isCheck(): bool
    {
        return self::Check === $this;
    }

    /** The suffix the command's title carries, so every mode is visible in the output. */
    public function titleSuffix(): string
    {
        return match ($this) {
            self::Write  => '',
            self::DryRun => ' (dry run)',
            self::Check  => ' (check)',
        };
    }
}
