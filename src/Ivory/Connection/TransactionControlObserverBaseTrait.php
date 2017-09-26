<?php
declare(strict_types=1);

namespace Ivory\Connection;

/**
 * Base implementation of a transaction control observer. Implements all handlers as empty methods.
 *
 * Recommended to be used by any {@link ITransactionControlObserver} implementations for future compatibility.
 */
trait TransactionControlObserverBaseTrait
{
    public function handleTransactionStart(): void
    {
    }

    public function handleTransactionCommit(): void
    {
    }

    public function handleTransactionRollback(): void
    {
    }

    public function handleSavepointSaved(string $name): void
    {
    }

    public function handleSavepointReleased(string $name): void
    {
    }

    public function handleRollbackToSavepoint(string $name): void
    {
    }

    public function handleTransactionPrepared(string $name): void
    {
    }

    public function handlePreparedTransactionCommit(string $name): void
    {
    }

    public function handlePreparedTransactionRollback(string $name): void
    {
    }
}
