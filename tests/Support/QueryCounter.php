<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Support;

use Psr\Log\AbstractLogger;

/**
 * Counts the database round trips a test makes.
 *
 * Wired into {@see \Tmi\TranslationBundle\Test\TestKernel} as the logger behind
 * DBAL's own {@see \Doctrine\DBAL\Logging\Middleware}, which debug-logs exactly
 * one message per executed statement or query -- see
 * {@see \Doctrine\DBAL\Logging\Statement::execute()} and
 * {@see \Doctrine\DBAL\Logging\Connection::query()}/{@see \Doctrine\DBAL\Logging\Connection::exec()}.
 * Transaction control (`beginTransaction()`/`commit()`/`rollBack()`) logs under a
 * different message and is deliberately not counted, so a flush()'s implicit
 * transaction never inflates a query budget.
 */
final class QueryCounter extends AbstractLogger
{
    private int $count = 0;

    /**
     * @param array<mixed> $context
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $message = (string) $message;

        if (str_starts_with($message, 'Executing statement:') || str_starts_with($message, 'Executing query:')) {
            ++$this->count;
        }
    }

    public function count(): int
    {
        return $this->count;
    }

    public function reset(): void
    {
        $this->count = 0;
    }
}
