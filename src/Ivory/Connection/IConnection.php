<?php
namespace Ivory\Connection;

use Ivory\Exception\ConnectionException;
use Ivory\Exception\InvalidStateException;

/**
 * Connection to a database.
 *
 * Apart from actually arranging the communication between the user code and a PostgreSQL database, the interface also
 * provides means of controlling various aspects of the session itself, especially the transaction control. These might
 * of course be also managed by custom SQL queries sent to the database; circumventing the designated methods of this
 * interface should be avoided, though, because the <tt>IConnection</tt> object tracks the state of the connection and
 * would end up in an undefined state if it was not aware of some control queries.
 *
 * @todo consider splitting this big interface into smaller parts, each responsible for one area (SessionManager...); then, the big Connection class might still exist, but serve just as a facade
 */
interface IConnection
{
	/**
	 * @return string name of the connection
	 */
	function getName();


	//region Connection control

	/**
	 * @return ConnectionParameters parameters for establishing the connection
	 */
	function getParameters();

	/**
	 * @return bool|null <tt>true</tt> if the connection is working,
	 *                   <tt>null</tt> if no connection is established (yet),
	 *                   <tt>false</tt> if the connection is established, but broken
	 */
	function isConnected();

	/**
	 * Establishes a connection with the database according to the current connection parameters, if it has not been
	 * established yet for this connection object.
	 *
	 * The connection is not shared with any other <tt>IConnection</tt> objects.
	 *
	 * TODO: on PHP 5.6.0+, connect asynchronously, then poll upon the first command actually requiring the connection; introduce a connectWait() variant which connects synchronously
	 *
	 * @return bool <tt>true</tt> if the connection has actually been established,
	 *              <tt>false</tt> if the connection has already been open and thus this was a no-op
	 * @throws ConnectionException on error connecting to the database
	 */
	function connect();

	/**
	 * Closes the connection (if any). The current transaction (if any) is rolled back.
	 *
	 * @return bool <tt>true</tt> if the connection has actually been closed,
	 *              <tt>false</tt> if no connection was established and thus this was a no-op
	 * @throws ConnectionException on error closing the connection
	 */
	function disconnect();

	//endregion


	//region Session control

	/**
	 * @return ConnConfig runtime configuration of the connection
	 */
	function getConfig();

	//endregion


	//region Transaction control

	/**
	 * Begins a new transaction.
	 *
	 * Note that transactions cannot be nested in PostgreSQL (use {@link savepoint()} instead). Thus, if not called
	 * inside an active transaction, just a warning is issued, nothing is actually performed, and <tt>false</tt> is
	 * returned.
	 *
	 * Transaction options may be specified, overriding the defaults set up by {@link setupSubsequentTransactions()}.
	 *
	 * @param int|TxConfig $transactionOptions options for the transaction to be started, overriding the defaults;
	 *                                         a {@link TxConfig} object, or a combination of {@link TxConfig} constants
	 * @return bool <tt>true</tt> if a new transaction has actually been started,
	 *              <tt>false</tt> if another transaction is already active and thus this was a no-op
	 */
	function startTransaction($transactionOptions = 0);

	/**
	 * Sets up the current transaction according to the given options.
	 *
	 * @param int|TxConfig $transactionOptions options for the transaction to be started;
	 *                                         a {@link TxConfig} object, or a combination of {@link TxConfig} constants
	 */
	function setupTransaction($transactionOptions);

	/**
	 * Sets up the default options for subsequent transactions started in the current session.
	 *
	 * The current transaction (if any) is unaffected by this command. See {@link setupTransaction()} instead.
	 *
	 * @param int|TxConfig $transactionOptions options for the transaction to be started;
	 *                                         a {@link TxConfig} object, or a combination of {@link TxConfig} constants
	 */
	function setupSubsequentTransactions($transactionOptions);

	/**
	 * Sets the current transaction to run with the same snapshot as another existing (still running) transaction.
	 *
	 * There are several notes on importing snapshots from other transactions. Most notably, it is only allowed at the
	 * beginning of the importing transaction, it only makes sense for the {@link TxConfig::SERIALIZABLE} or the
	 * {@link TxConfig::REPEATABLE_READ} isolation level of the importing transaction, and it merely synchronizes the
	 * transactions with respect to pre-existing data. See the PostgreSQL documentation on
	 * {@link http://www.postgresql.org/docs/9.4/static/sql-set-transaction.html <tt>SQL SET TRANSACTION</tt>} and
	 * {@link http://www.postgresql.org/docs/9.4/static/functions-admin.html#FUNCTIONS-SNAPSHOT-SYNCHRONIZATION Snapshot Synchronization Functions}
	 * for detailed information.
	 *
	 * If no transaction is actually active, just a warning is issued, nothing is performed, and <tt>false</tt> is
	 * returned.
	 *
	 * @param string $snapshotId a snapshot identifier exported by another existing transaction using
	 *                             {@link exportTransactionSnapshot()} (or just using a custom call to the
	 *                             <tt>pg_export_snapshot()</tt> PostgreSQL function)
	 * @return bool <tt>true</tt> if the transaction snapshot has actually been imported,
	 *              <tt>false</tt> if no transaction was active and thus this was a no-op
	 */
	function setTransactionSnapshot($snapshotId);

	/**
	 * Saves the current transaction snapshot and returns its identifier for a later use to
	 * {@link setTransactionSnapshot()}.
	 *
	 * See {@link setTransactionSnapshot()} for more information.
	 *
	 * Once a transaction exports its snapshot, it cannot be {@link prepareTransaction() prepared} for a two-phase
	 * commit.
	 *
	 * @return string|null the exported snapshot identifier, or <tt>null</tt> if not inside any transaction
	 */
	function exportTransactionSnapshot();

	/**
	 * Commits the active transaction.
	 *
	 * If no transaction is actually active, just a warning is issued, nothing is performed, and <tt>false</tt> is
	 * returned.
	 *
	 * @return bool <tt>true</tt> if the transaction has actually been committed,
	 *              <tt>false</tt> if no transaction was active and thus this was a no-op
	 */
	function commit();

	/**
	 * Rolls back the active transaction.
	 *
	 * If no transaction is actually active, just a warning is issued, nothing is performed, and <tt>false</tt> is
	 * returned.
	 *
	 * @return bool <tt>true</tt> if the transaction has actually been rolled back,
	 *              <tt>false</tt> if no transaction was active and thus this was a no-op
	 */
	function rollback();

	/**
	 * Defines a new savepoint within the active transaction.
	 *
	 * Use {@link rollbackToSavepoint()} to go back to the state of the savepoint, or {@link releaseSavepoint()} to
	 * release it so that it does not take the database system resources anymore.
	 *
	 * In PostgreSQL, the same name may be used for multiple savepoints within a transaction at a time. The operations
	 * on the savepoints always work with the most recent savepoint of a given name.
	 *
	 * If no transaction is active, an <tt>InvalidStateException</tt> is thrown. Note this is different from operations
	 * controlling the whole transaction, which issue just a warning. This mimics the PostgreSQL behaviour, and is
	 * generally safer for savepoints.
	 *
	 * @param string $name name for the new savepoint
	 * @throws InvalidStateException when not inside a transaction
	 */
	function savepoint($name);

	/**
	 * Rolls back all commands executed after the given savepoint.
	 *
	 * All commands are rolled back, including creation of new savepoints. That is, all savepoints established after the
	 * given savepoint are destroyed. The given savepoint itself remains valid, allowing to roll back to it multiple
	 * times.
	 *
	 * If no transaction is active, an <tt>InvalidStateException</tt> is thrown. Note this is different from operations
	 * controlling the whole transaction, which issue just a warning. This mimics the PostgreSQL behaviour, and is
	 * generally safer for savepoints.
	 *
	 * Database error on trying to roll back to an undefined savepoint is handled and thrown as an
	 * <tt>InvalidStateException</tt>, too.
	 *
	 * @see http://www.postgresql.org/docs/9.4/static/sql-rollback-to.html PostgreSQL docs on details regarding cursors
	 *
	 * @param string $name name of the savepoint to roll back to;
	 *                     if there are multiple savepoints of such name, the most recent is chosen
	 * @throws InvalidStateException when not inside a transaction,
	 *                               or if trying to roll back to an undefined savepoint
	 */
	function rollbackToSavepoint($name);

	/**
	 * Destroys the given savepoint, and all savepoints established after it. Thus, it is not possible to roll back to
	 * those savepoints anymore.
	 *
	 * Note the operation does not undo the effects of commands executed after the given savepoint was established.
	 * It merely frees some database system resources. Use {@link rollbackToSavepoint()} to undo the commands.
	 *
	 * @param string $name name of the savepoint to release;
	 *                     if there are multiple savepoints of such name, the most recent is chosen
	 * @throws InvalidStateException when not inside a transaction,
	 *                               or if trying to release an undefined savepoint,
	 *                               or if the transaction is in an aborted state
	 */
	function releaseSavepoint($name);

	/**
	 * Prepares the current transaction for a two-phase commit.
	 *
	 * Store the state of the current transaction to disk and rolls it back for the current session. The only exception
	 * are configuration parameter changes, which are committed by this command, and are not part of the transaction
	 * state stored to disk.
	 *
	 * The prepared transaction can later be committed or rolled back from any session using
	 * {@link commitPreparedTransaction()} or {@link rollbackPreparedTransaction()}, respectively. To list existing
	 * prepared transactions, {@link listPreparedTransactions()} may be helpful.
	 *
	 * This command is only relevant inside a transaction. It has no effect when not inside a transaction, just a
	 * warning will be issued.
	 *
	 * @see http://www.postgresql.org/docs/9.4/static/sql-prepare-transaction.html
	 *
	 * @param string $name name for the prepared transaction; must be server-wide unique
	 * @return bool <tt>true</tt> if the transaction has actually been prepared,
	 *              <tt>false</tt> if no transaction was active and thus this was a no-op
	 */
	function prepareTransaction($name);

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
	function commitPreparedTransaction($name);

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
	function rollbackPreparedTransaction($name);

	/**
	 * Lists all transactions which are currently prepared for a two-phase commit.
	 *
	 * See {@link prepareTransaction()} for more details on preparing a transaction.
	 *
	 * @todo specify the return type - the standard result of a query
	 */
	function listPreparedTransactions();

	//endregion


	//region IPC control

	/**
	 * Returns ID of the database server process. This might be useful for comparison with PID of a notifying process.
	 *
	 * @return int
	 */
	function getBackendPID();

	/**
	 * Notifies all listeners of a given channel.
	 *
	 * See the {@see http://www.postgresql.org/docs/9.4/static/sql-notify.html PostgreSQL documentation} for details
	 * regarding the asynchronous notifications, especially the behaviour regarding transactions.
	 *
	 * @param string $channel name of channel to send notification to
	 * @param string|null $payload optional payload to send along with the notification;
	 *                             note the maximal accepted length depends on the database configuration
	 */
	function notify($channel, $payload = null);

	/**
	 * Starts listening to the specified channel.
	 *
	 * Calling this method merely enables the current session to receive notifications through the given channel. To
	 * actually get some, use {@link pollNotification()}.
	 *
	 * @param string $channel name of channel to listen to
	 */
	function listen($channel);

	/**
	 * Stops listening to the specified channel.
	 *
	 * Use {@link unlistenAll()} to stop listening from all channels currently listening to.
	 *
	 * @param string $channel name of channel to stop listening to
	 */
	function unlisten($channel);

	/**
	 * Stops listening to any channel.
	 *
	 * Use {@link unlisten()} to stop listening only to a specified channel.
	 */
	function unlistenAll();

	/**
	 * Polls the queue of waiting IPC notifications on channels being listened to.
	 *
	 * @return Notification|null the next notification in the queue, or <tt>null</tt> if there is no notification there
	 */
	function pollNotification();

	//endregion
}
