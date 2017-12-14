<?php
namespace Ivory\Connection;

use Ivory\Exception\InvalidStateException;
use Ivory\Result\IQueryResult;

/**
 * Transaction control.
 *
 * Note that the started transaction is represented by an {@link ITxHandle}. Further control over the transaction is
 * done through this object. Thus, this interface is rather narrow - the rest of the methods one would expect from the
 * transaction control ({@link ITxHandle::commit() commit()}, {@link ITxHandle::rollback() rollback()}, etc.) are
 * defined on {@link ITxHandle} instead.
 *
 * Also note the API is rather strict - exceptions are thrown when a command is issued in an invalid state.
 */
interface ITransactionControl
{
    /**
     * Finds out whether a transaction is currently active.
     *
     * @return bool <tt>true</tt> iff a transaction is active
     */
    function inTransaction(): bool;

    /**
     * Begins a new transaction.
     *
     * The started transaction may further be controlled using an {@link ITxHandle} returned by this method.
     *
     * Note that transactions cannot be nested in PostgreSQL. Thus, an InvalidStateException is thrown if a transaction
     * is already active. {@link ITxHandle::savepoint() Savepoints} may be used instead on the returned handle.
     *
     * Transaction options may be specified, overriding the defaults set up by {@link setupSubsequentTransactions()}.
     *
     * @internal Ivory design note: Usual database access layers only define a thin API for transaction control,
     * offering all the transaction control commands on the connection itself. In the real world, however, one must keep
     * track of where exactly the transaction is open and when it comes closed (either committed or rolled back). To
     * make this fact more explicit, the transaction is represented by a handle, only through which it may further be
     * controlled. It is clear, then, which methods are expected to be called within an open transaction, and which
     * control commands are, conversely, to be executed outside of a transaction.
     *
     * @param int|TxConfig $transactionOptions options for the transaction to be started, overriding the defaults;
     *                                         a {@link TxConfig} object, or a combination of {@link TxConfig} constants
     * @return ITxHandle handle of the started transaction
     * @throws InvalidStateException when already inside a transaction
     */
    function startTransaction($transactionOptions = 0): ITxHandle;

    /**
     * Sets up the default options for new transactions started in the current session.
     *
     * The current transaction (if any) is unaffected by this command. See {@link ITxHandle::setupTransaction()}
     * instead.
     *
     * @param int|TxConfig $transactionOptions options for the transaction to be started;
     *                                         a {@link TxConfig} object, or a combination of {@link TxConfig} constants
     */
    function setupSubsequentTransactions($transactionOptions): void;

    /**
     * Retrieves the transaction parameters effective for new transactions.
     *
     * To get the current transaction parameters, use {@link ITxHandle::getTxConfig()} on the transaction handle.
     *
     * @return TxConfig default transaction parameters for new transactions
     */
    function getDefaultTxConfig(): TxConfig;

    /**
     * Commits a transaction prepared for a two-phase commit.
     *
     * Not to be performed inside a transaction.
     *
     * @see http://www.postgresql.org/docs/9.4/static/sql-commit-prepared.html
     *
     * @param string $name name of the prepared transaction
     * @throws InvalidStateException when inside a transaction
     */
    function commitPreparedTransaction(string $name): void;

    /**
     * Rolls back a transaction prepared for a two-phase commit.
     *
     * Not to be performed inside a transaction.
     *
     * @see http://www.postgresql.org/docs/9.4/static/sql-rollback-prepared.html
     *
     * @param string $name name of the prepared transaction
     * @throws InvalidStateException when inside a transaction
     */
    function rollbackPreparedTransaction(string $name): void;

    /**
     * Lists all transactions which are currently prepared for a two-phase commit.
     *
     * See {@link ITxHandle::prepareTransaction()} for more details on preparing a transaction.
     *
     * @return IQueryResult result of query to all columns of
     *                        {@link http://www.postgresql.org/docs/9.4/static/view-pg-prepared-xacts.html <tt>pg_catalog.pg_prepared_xacts</tt>}
     */
    function listPreparedTransactions(): IQueryResult;
}
