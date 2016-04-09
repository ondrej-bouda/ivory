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

    public function handleSavepointSaved($name)
    {
    }

    public function handleSavepointReleased($name)
    {
    }

    public function handleRollbackToSavepoint($name)
    {
    }

    public function handleTransactionPrepared($name)
    {
    }

    public function handlePreparedTransactionCommit($name)
    {
    }

    public function handlePreparedTransactionRollback($name)
    {
    }
}
