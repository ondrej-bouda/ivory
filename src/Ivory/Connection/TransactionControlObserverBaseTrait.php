<?php
namespace Ivory\Connection;

/**
 * Base implementation of a transaction control observer. Implements all handlers as empty methods.
 *
 * Recommended to be used by any {@link ITransactionControlObserver} implementations for future compatibility.
 */
trait TransactionControlObserverBaseTrait
{
    public function handleTransactionStart()
    {
    }

    public function handleTransactionCommit()
    {
    }

    public function handleTransactionRollback()
    {
    }

    public function handleSavepointSaved(string $name)
    {
    }

    public function handleSavepointReleased(string $name)
    {
    }

    public function handleRollbackToSavepoint(string $name)
    {
    }

    public function handleTransactionPrepared(string $name)
    {
    }

    public function handlePreparedTransactionCommit(string $name)
    {
    }

    public function handlePreparedTransactionRollback(string $name)
    {
    }
}
