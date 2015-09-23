<?php
namespace Ivory\Connection;

use Ivory\Exception\InvalidStateException;
use Ivory\Utils\NotSerializable;

class Connection implements IConnection
{
	use NotSerializable; // TODO: implement connection serialization instead of giving up

	private $name;
	private $connCtl;
	private $cmdExec;

	private $config;

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
		$this->connCtl = new ConnectionControl($params);
		$this->cmdExec = new CommandExecution($this->connCtl);
		$this->config = new ConnConfig($this);
	}

	final public function getName()
	{
		return $this->name;
	}

	final public function getConfig()
	{
		return $this->config;
	}

	public function startTransaction($transactionOptions = 0)
	{
		$this->connCtl->requireConnection();
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
		$this->connCtl->requireConnection();
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
		$this->connCtl->requireConnection();
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
		$this->connCtl->requireConnection();
		if (!$this->inTransaction) {
			throw new InvalidStateException('No transaction is active, cannot create any savepoint.');
		}

		// TODO: issue SAVEPOINT "$name"
		// TODO: adjust the savepoints info
		// TODO: issue an event in case someone is listening
	}

	public function rollbackToSavepoint($name)
	{
		$this->connCtl->requireConnection();
		if (!$this->inTransaction) {
			throw new InvalidStateException('No transaction is active, cannot roll back to any savepoint.');
		}

		// TODO: issue ROLLBACK TO SAVEPOINT "$name"
		// TODO: adjust the savepoints info
		// TODO: issue an event in case someone is listening
	}

	public function releaseSavepoint($name)
	{
		$this->connCtl->requireConnection();
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
		$handler = $this->connCtl->requireConnection();

		$res = pg_get_notify($handler, PGSQL_ASSOC);
		if ($res === false) {
			return null;
		}
		return new Notification($res['message'], $res['pid'], $res['payload']);
	}


	//region Connection Control

	public function getParameters()
	{
		return $this->connCtl->getParameters();
	}

	public function isConnected()
	{
		return $this->connCtl->isConnected();
	}

	public function isConnectedWait()
	{
		return $this->connCtl->isConnectedWait();
	}

	public function connect()
	{
		return $this->connCtl->connect();
	}

	public function connectWait()
	{
		return $this->connCtl->connectWait();
	}

	public function disconnect()
	{
		return $this->connCtl->disconnect();
	}

	//endregion

	//region Command Execution

	public function rawQuery($sqlStatement)
	{
		return $this->cmdExec->rawQuery($sqlStatement);
	}

	public function rawMultiQuery($sqlStatements)
	{
		return $this->cmdExec->rawMultiQuery($sqlStatements);
	}

	public function runScript($sqlScript)
	{
		return $this->cmdExec->runScript($sqlScript);
	}

	//endregion
}