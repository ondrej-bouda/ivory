<?php
namespace Ivory\Connection;

use Ivory\Exception\ConnectionException;
use Ivory\Exception\InvalidStateException;
use Ivory\Exception\ResultException;
use Ivory\Utils\NotSerializable;

class Connection implements IConnection
{
	use NotSerializable; // TODO: implement connection serialization instead of giving up

	private $name;
	private $params;
	private $config;

	/** @var resource|null the connection handler, or null if connection was not requested or already closed */
	private $handler = null;
	/** @var bool whether the asynchronous connecting was finished (<tt>true</tt> if synchronous connecting was used) */
	private $finishedConnecting;

	private $inTransaction = false; // FIXME: rely on pg_transaction_status() instead of duplicating its logic here (it is reported not to talk to the server, so it shall be fast)

	/**
	 * @param string $name name for the connection
	 * @param ConnectionParameters|array|string $params either a connection parameters object, or an associative array
	 *                                                    of parameters for {@link ConnectionParameters::__construct()},
	 *                                                    or a URL for {@link ConnectionParameters::fromUrl()},
	 *                                                    or a PostgreSQL connection string (see {@link pg_connect()})
	 */
	public function __construct($name, $params)
	{
		$this->name = $name;
		$this->params = ConnectionParameters::create($params);
		$this->config = new ConnConfig($this);
	}

	final public function getName()
	{
		return $this->name;
	}

	final public function getParameters()
	{
		return $this->params;
	}

	final public function getConfig()
	{
		return $this->config;
	}

	public function isConnected()
	{
		if ($this->handler === null) {
			return null;
		}
		else {
			return (pg_connection_status($this->handler) === PGSQL_CONNECTION_OK);
		}
	}

	public function isConnectedWait()
	{
		if ($this->handler === null) {
			return null;
		}
		else {
			$this->waitForConnection();
			return (pg_connection_status($this->handler) === PGSQL_CONNECTION_OK);
		}
	}

	public function connect()
	{
		if ($this->handler === null) {
			$this->openConnection(PGSQL_CONNECT_ASYNC);
			return true;
		}
		else {
			return false;
		}
	}

	public function connectWait()
	{
		if ($this->handler === null) {
			$this->openConnection();
			return true;
		}
		else {
			$this->waitForConnection();
			return false;
		}
	}

	private function openConnection($connectFlags = 0)
	{
		$connStr = $this->params->buildConnectionString();
		$this->handler = pg_connect($connStr, PGSQL_CONNECT_FORCE_NEW | $connectFlags);
		$this->finishedConnecting = !($connectFlags & PGSQL_CONNECT_ASYNC);
		if ($this->handler === false || pg_connection_status($this->handler) === PGSQL_CONNECTION_BAD) {
			$this->handler = null;
			throw new ConnectionException('Error connecting to the database'); // TODO: try to squeeze the client to get some useful information; maybe trap errors issued by pg_connect()?
		}
	}

	private function waitForConnection()
	{
		if ($this->finishedConnecting) {
			return;
		}
		if ($this->handler === null) {
			throw new InvalidStateException('Expected to be called only on a started connection.');
		}

		$pollStatus = pg_connect_poll($this->handler);
		if ($pollStatus === PGSQL_POLLING_OK) {
			$this->finishedConnecting = true;
			return;
		}

		$socket = pg_socket($this->handler);
		if (!$socket) {
			throw new ConnectionException('Error retrieving the connection socket while trying to wait for connection');
		}

		while (true) {
			switch ($pollStatus) {
				case PGSQL_POLLING_OK:
					$this->finishedConnecting = true;
					return;
				case PGSQL_POLLING_FAILED:
					throw new ConnectionException('Failed waiting for connection');
				case PGSQL_POLLING_READING:
					while (!self::isStreamReadable($socket, 1)); // TODO: make the timeout configurable
					break;
				default:
					usleep(10); // a micro sleep in other polling states seems better than a busy wait
					// TODO: make the micro sleep duration configurable, allowing 0 to effectively force busy wait
			}

			$pollStatus = pg_connect_poll($this->handler);
		}
	}

	private static function isStreamReadable($stream, $sec, $usec = null)
	{
		$r = [$stream];
		$w = [];
		$ex = [];
		return (bool)stream_select($r, $w, $ex, $sec, $usec);
	}

	public function disconnect()
	{
		if ($this->handler === null) {
			return false;
		}

		// TODO: handle the case when there are any open LO handles (an LO shall be an IType registered at the connection)

		if ($this->inTransaction) {
			$this->rollback();
		}

		$closed = pg_close($this->handler); // NOTE: it seems correct to close a not yet established asynchronous connection
		if (!$closed) {
			throw new ConnectionException('Error closing the connection');
		}
		$this->handler = null;
		$this->finishedConnecting = null;
		return true;
	}

	/**
	 * Connects to the database if not already connected or if the connection has been lost.
	 *
	 * If an asynchronous connection has been requested, it waits until it is finished, and then returns.
	 *
	 * After returning, it guarantees that <tt>$this->handler</tt> is a valid resource on an established connection,
	 * ready for executing queries.
	 *
	 * @throws ConnectionException on error connecting to the database
	 */
	private function requireConnection()
	{
		$this->connectWait(); // it is a no-op if already connected
		// NOTE: the meaning of this method is to later allow control whether the auto-connecting shall be enabled, and
		//       to provide a possible entry-point for reconnecting upon a broken connection
	}

	public function startTransaction($transactionOptions = 0)
	{
		$this->requireConnection();
		if ($this->inTransaction) {
			trigger_error('A transaction is already active, cannot start a new one.', E_USER_WARNING);
			return;
		}

		// TODO: issue START TRANSACTION
		// TODO: adjust the savepoint info
		// TODO: issue an event in case someone is listening

		$this->inTransaction = true;
	}

	public function setupTransaction($transactionOptions)
	{
		// TODO: Implement setupTransaction() method.
	}

	public function setupSubsequentTransactions($transactionOptions)
	{
		// TODO: Implement setupSubsequentTransactions() method.
	}

	public function commit()
	{
		$this->requireConnection();
		if (!$this->inTransaction) {
			trigger_error('No transaction is active, nothing to commit.', E_USER_WARNING);
			return;
		}

		// TODO: issue COMMIT
		// TODO: adjust the savepoint info
		// TODO: issue an event in case someone is listening

		$this->inTransaction = false;
	}

	public function rollback()
	{
		$this->requireConnection();
		if (!$this->inTransaction) {
			trigger_error('No transaction is active, nothing to roll back.', E_USER_WARNING);
			return;
		}

		// TODO: issue ROLLBACK
		// TODO: adjust the savepoints info
		// TODO: issue an event in case someone is listening

		$this->inTransaction = false;
	}

	public function savepoint($name)
	{
		$this->requireConnection();
		if (!$this->inTransaction) {
			throw new InvalidStateException('No transaction is active, cannot create any savepoint.');
		}

		// TODO: issue SAVEPOINT "$name"
		// TODO: adjust the savepoints info
		// TODO: issue an event in case someone is listening
	}

	public function rollbackToSavepoint($name)
	{
		$this->requireConnection();
		if (!$this->inTransaction) {
			throw new InvalidStateException('No transaction is active, cannot roll back to any savepoint.');
		}

		// TODO: issue ROLLBACK TO SAVEPOINT "$name"
		// TODO: adjust the savepoints info
		// TODO: issue an event in case someone is listening
	}

	public function releaseSavepoint($name)
	{
		$this->requireConnection();
		if (!$this->inTransaction) {
			throw new InvalidStateException('No transaction is active, cannot release any savepoint.');
		}

		// TODO: issue RELEASE SAVEPOINT "$name"
		// TODO: adjust the savepoints info
		// TODO: issue an event in case someone is listening
	}

	public function setTransactionSnapshot($snapshotId)
	{
		// TODO: Implement setTransactionSnapshot() method.
	}

	public function exportTransactionSnapshot()
	{
		// TODO: Implement exportTransactionSnapshot() method.
	}

	public function prepareTransaction($name)
	{
		// TODO: Implement prepareTransaction() method.
	}

	public function commitPreparedTransaction($name)
	{
		// TODO: Implement commitPreparedTransaction() method.
	}

	public function rollbackPreparedTransaction($name)
	{
		// TODO: Implement rollbackPreparedTransaction() method.
	}

	public function listPreparedTransactions()
	{
		// TODO: fix the return type of this method, specified by the interface
		// TODO: query pg_catalog.pg_prepared_xacts
	}

	public function getBackendPID()
	{
		// TODO: Implement getBackendPID() method.
	}

	public function notify($channel, $payload = null)
	{
		// TODO: Implement notify() method.
	}

	public function listen($channel)
	{
		// TODO: Implement listen() method.
	}

	public function unlisten($channel)
	{
		// TODO: Implement unlisten() method.
	}

	public function unlistenAll()
	{
		// TODO: Implement unlistenAll() method.
	}

	public function pollNotification()
	{
		$this->requireConnection();

		$res = pg_get_notify($this->handler, PGSQL_ASSOC);
		if ($res === false) {
			return null;
		}
		return new Notification($res['message'], $res['pid'], $res['payload']);
	}

	public function query($sql)
	{
		$this->requireConnection();

		// FIXME: use async query instead to get error details
		$res = @pg_query($this->handler, $sql); // @: errors are expected; they are treated by throwing an exception
		if ($res === false) {
			throw new ResultException();
		}
	}
}
