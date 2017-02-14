<?php
namespace Ivory\Connection;

interface ITransactionControlObserver
{
    /**
     * Called upon starting a new transaction.
     */
    function handleTransactionStart();

    /**
     * Called upon committing the current transaction.
     */
    function handleTransactionCommit();

    /**
     * Called upon rolling back the current transaction.
     */
    function handleTransactionRollback();

    /**
     * Called upon saving the current transaction status to a savepoint.
     *
     * @param string $name name of the savepoint
     */
    function handleSavepointSaved(string $name);

    /**
     * Called upon releasing a savepoint.
     *
     * @param string $name name of the released savepoint
     */
    function handleSavepointReleased(string $name);

    /**
     * Called upon rolling back to a savepoint.
     *
     * @param string $name name of the savepoint rolled back to
     */
    function handleRollbackToSavepoint(string $name);

    /**
     * Called after a transaction gets prepared for a two-phase commit.
     *
     * @param string $name name of the prepared transaction
     */
    function handleTransactionPrepared(string $name);

    /**
     * Called upon committing a prepared transaction.
     *
     * @param string $name name of the prepared transaction
     */
    function handlePreparedTransactionCommit(string $name);

    /**
     * Called upon rollback of a prepared transaction.
     *
     * @param string $name name of the prepared transaction
     */
    function handlePreparedTransactionRollback(string $name);
}
