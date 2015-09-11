<?php
namespace Ivory;

use Ivory\Utils\NotSerializable;

class Connection implements IConnection
{
	use NotSerializable; // TODO: implement connection serialization instead of giving up

	private $name;
	private $params;
	private $config;

	/** @var resource the connection handler */
	private $handler = null;

	private $inTransaction = false;

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
			// NOTE: pg_connection_status() may also return null
			return (pg_connection_status($this->handler) === PGSQL_CONNECTION_OK);
		}
	}

	public function connect()
	{
		if ($this->handler !== null) {
			return false;
		}

		$this->handler = pg_connect($this->params->buildConnectionString(), PGSQL_CONNECT_FORCE_NEW);
		if ($this->handler === false) {
			throw new ConnectionException('Error connecting to the database');
		}

		return true;
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

		$closed = pg_close($this->handler);
		if (!$closed) {
			throw new ConnectionException('Error closing the connection');
		}
		$this->handler = null;
		return true;
	}

	/**
	 * Connects to the database if not already connected.
	 */
	private function autoConnect()
	{
		$this->connect(); // it is a no-op if already connected
		// NOTE: the meaning of this method is to later allow control whether the auto-connecting shall be enabled
	}

	public function startTransaction($transactionOptions = 0)
	{
		$this->autoConnect();
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
		$this->autoConnect();
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
		$this->autoConnect();
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
		$this->autoConnect();
		if (!$this->inTransaction) {
			throw new InvalidStateException('No transaction is active, cannot create any savepoint.');
		}

		// TODO: issue SAVEPOINT "$name"
		// TODO: adjust the savepoints info
		// TODO: issue an event in case someone is listening
	}

	public function rollbackToSavepoint($name)
	{
		$this->autoConnect();
		if (!$this->inTransaction) {
			throw new InvalidStateException('No transaction is active, cannot roll back to any savepoint.');
		}

		// TODO: issue ROLLBACK TO SAVEPOINT "$name"
		// TODO: adjust the savepoints info
		// TODO: issue an event in case someone is listening
	}

	public function releaseSavepoint($name)
	{
		$this->autoConnect();
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

	/**
	 * Returns ID of the database server process. This might be useful for comparison with PID of a notifying process.
	 *
	 * @return int
	 */
	function getBackendPID()
	{
		// TODO: Implement getBackendPID() method.
	}

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
	function notify($channel, $payload = null)
	{
		// TODO: Implement notify() method.
	}

	/**
	 * Starts listening to the specified channel.
	 *
	 * Calling this method merely enables the current session to receive notifications through the given channel. To
	 * actually get some, use {@link pollNotification()}.
	 *
	 * @param string $channel name of channel to listen to
	 */
	function listen($channel)
	{
		// TODO: Implement listen() method.
	}

	/**
	 * Stops listening to the specified channel.
	 *
	 * Use {@link unlistenAll()} to stop listening from all channels currently listening to.
	 *
	 * @param string $channel name of channel to stop listening to
	 */
	function unlisten($channel)
	{
		// TODO: Implement unlisten() method.
	}

	/**
	 * Stops listening to any channel.
	 *
	 * Use {@link unlisten()} to stop listening only to a specified channel.
	 */
	function unlistenAll()
	{
		// TODO: Implement unlistenAll() method.
	}

	/**
	 * Polls the queue of waiting IPC notifications on channels being listened to.
	 *
	 * @return Notification|null the next notification in the queue, or <tt>null</tt> if there is no notification there
	 */
	function pollNotification()
	{
		$this->autoConnect();
		$res = pg_get_notify($this->handler, PGSQL_ASSOC);
		if ($res === false) {
			return null;
		}
		return new Notification($res['message'], $res['pid'], $res['payload']);
	}
}
