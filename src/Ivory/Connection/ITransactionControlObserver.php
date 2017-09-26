<?php
namespace Ivory\Connection;

interface ITransactionControlObserver
{
    /**
     * Called upon starting a new transaction.
     */
    function handleTransactionStart(): void;

    /**
     * Called upon committing the current transaction.
     */
    function handleTransactionCommit(): void;

    /**
     * Called upon rolling back the current transaction.
     */
    function handleTransactionRollback(): void;

    /**
     * Called upon saving the current transaction status to a savepoint.
     *
     * @param string $name name of the savepoint
     */
    function handleSavepointSaved(string $name): void;

    /**
     * Called upon releasing a savepoint.
     *
     * @param string $name name of the released savepoint
     */
    function handleSavepointReleased(string $name): void;

    /**
     * Called upon rolling back to a savepoint.
     *
     * @param string $name name of the savepoint rolled back to
     */
    function handleRollbackToSavepoint(string $name): void;

    /**
     * Called after a transaction gets prepared for a two-phase commit.
     *
     * @param string $name name of the prepared transaction
     */
    function handleTransactionPrepared(string $name): void;

    /**
     * Called upon committing a prepared transaction.
     *
     * @param string $name name of the prepared transaction
     */
    function handlePreparedTransactionCommit(string $name): void;

    /**
     * Called upon rollback of a prepared transaction.
     *
     * @param string $name name of the prepared transaction
     */
    function handlePreparedTransactionRollback(string $name): void;
}
