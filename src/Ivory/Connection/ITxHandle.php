<?php
namespace Ivory\Connection;

use Ivory\Exception\InvalidStateException;
use Ivory\Exception\UndefinedSavepointException;

/**
 * Handle of an open transaction.
 *
 * The handle is a stateful object, reflecting the state of the transaction at the database connection. Once closed, the
 * object may not be used any further, only throwing an {@link InvalidStateException} - instead, a new transaction must
 * be started on the connection.
 */
interface ITxHandle
{
    /**
     * Tells whether the transaction is open and thus it may still be operated on, or it is already closed.
     */
    function isOpen(): bool;

    /**
     * Sets up the transaction according to the given options.
     *
     * Note that some options may only be set up before the first query or data-modifying statement is executed within
     * the transaction. See the PostgreSQL documentation on
     * {@link http://www.postgresql.org/docs/9.4/static/sql-set-transaction.html `SQL SET TRANSACTION`} for more
     * details.
     *
     * @param int|TxConfig $transactionOptions options for the transaction to be started;
     *                                         a {@link TxConfig} object, or a combination of {@link TxConfig} constants
     * @throws InvalidStateException if the transaction is not open anymore
     */
    function setupTransaction($transactionOptions): void;

    /**
     * Sets the transaction to run with the same snapshot as another existing (still running) transaction.
     *
     * There are several notes on importing snapshots from other transactions. Most notably, it is only allowed at the
     * beginning of the importing transaction, it only makes sense for the {@link TxConfig::SERIALIZABLE} or the
     * {@link TxConfig::REPEATABLE_READ} isolation level of the importing transaction, and it merely synchronizes the
     * transactions with respect to pre-existing data. See the PostgreSQL documentation on
     * {@link http://www.postgresql.org/docs/9.4/static/sql-set-transaction.html `SQL SET TRANSACTION`} and
     * {@link http://www.postgresql.org/docs/9.4/static/functions-admin.html#FUNCTIONS-SNAPSHOT-SYNCHRONIZATION Snapshot Synchronization Functions}
     * for detailed information.
     *
     * @param string $snapshotId a snapshot identifier exported by another existing transaction using
     *                             {@link exportTransactionSnapshot()} (or just using a custom call to the
     *                             <tt>pg_export_snapshot()</tt> PostgreSQL function)
     * @throws InvalidStateException if the transaction is not open anymore
     */
    function setTransactionSnapshot(string $snapshotId): void;

    /**
     * Saves the transaction snapshot and returns its identifier for a later use by {@link setTransactionSnapshot()}.
     *
     * Once a transaction exports its snapshot, it cannot be {@link prepareTransaction() prepared} for a two-phase
     * commit.
     *
     * @return string the exported snapshot identifier
     */
    function exportTransactionSnapshot(): string;

    /**
     * Commits the transaction.
     *
     * After the commit, the transaction is closed and the handle gets stale and, as such, it cannot be used anymore.
     *
     * @throws InvalidStateException if the transaction is not open anymore
     */
    function commit(): void;

    /**
     * Rolls back the transaction.
     *
     * After the rollback, the transaction is closed and the handle gets stale and, as such, it cannot be used anymore.
     *
     * @throws InvalidStateException if the transaction is not open anymore
     */
    function rollback(): void;

    /**
     * Rolls back the transaction provided it is still open. If the transaction is closed, this is a no-op.
     *
     * The intended usage of this method is to handle exceptions and errors thrown inside a transaction so that the
     * transaction is rolled back before the throwable gets raised outside of the scope:
     * <code>
     * $tx = $conn->startTransaction();
     * try {
     *     // statements which might throw an exception or error
     *     $tx->commit();
     * }
     * finally {
     *     $tx->rollbackIfOpen();
     * }
     * </code>
     *
     * After the rollback, the transaction is closed and the handle gets stale and, as such, it cannot be used anymore.
     */
    function rollbackIfOpen(): void;

    /**
     * Defines a new savepoint within the transaction.
     *
     * Use {@link rollbackToSavepoint()} to go back to the state of the savepoint, or {@link releaseSavepoint()} to
     * release it so that it does not take the database system resources anymore.
     *
     * In PostgreSQL, the same name may be used for multiple savepoints within a transaction at a time. The operations
     * on the savepoints always work with the most recent savepoint of a given name.
     *
     * @param string $name name for the new savepoint
     * @throws InvalidStateException if the transaction is not open anymore
     */
    function savepoint(string $name): void;

    /**
     * Rolls back all statements executed after the given savepoint.
     *
     * All statements are rolled back, including creation of new savepoints. That is, all savepoints established after
     * the given savepoint are destroyed. The given savepoint itself remains valid, allowing to roll back to it multiple
     * times.
     *
     * @see http://www.postgresql.org/docs/9.4/static/sql-rollback-to.html PostgreSQL docs on details regarding cursors
     *
     * @param string $name name of the savepoint to roll back to;
     *                     if there are multiple savepoints of such name, the most recent is chosen
     * @throws InvalidStateException if the transaction is not open anymore
     * @throws UndefinedSavepointException if trying to roll back to an undefined savepoint
     */
    function rollbackToSavepoint(string $name): void;

    /**
     * Destroys the given savepoint, and all savepoints established after it. Thus, it is not possible to roll back to
     * those savepoints anymore.
     *
     * Note the operation does not undo the effects of statements executed after the given savepoint was established.
     * It merely frees some database system resources. Use {@link rollbackToSavepoint()} to undo the statements.
     *
     * @param string $name name of the savepoint to release;
     *                     if there are multiple savepoints of such name, the most recent is chosen
     * @throws InvalidStateException if the transaction is not open anymore,
     *                               or if the transaction is in an aborted state
     * @throws UndefinedSavepointException if trying to roll back to an undefined savepoint
     */
    function releaseSavepoint(string $name): void;

    /**
     * Prepares the transaction for a two-phase commit.
     *
     * Stores the state of the transaction to disk and rolls it back for the current session. The only exception are
     * configuration parameter changes, which are committed by this command, and are not part of the transaction state
     * stored to disk.
     *
     * The prepared transaction can later be committed or rolled back from any session using
     * {@link ITransactionControl::commitPreparedTransaction()} or
     * {@link ITransactionControl::rollbackPreparedTransaction()}, respectively. To list existing prepared transactions,
     * {@link ITransactionControl::listPreparedTransactions()} may be helpful.
     *
     * @see http://www.postgresql.org/docs/9.4/static/sql-prepare-transaction.html
     *
     * @param string $name name for the prepared transaction; must be server-wide unique
     * @throws InvalidStateException if the transaction is not open anymore
     */
    function prepareTransaction(string $name): void;
}
