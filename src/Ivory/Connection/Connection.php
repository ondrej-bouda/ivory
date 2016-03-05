<?php
namespace Ivory\Connection;

use Ivory\Exception\InvalidStateException;
use Ivory\Utils\NotSerializable;

// TODO: consider renaming to Database or Session or... - to reflect it is mere a facade, the single entry point
// TODO: consider introducing factory methods for abbreviating relation creating; e.g., new QueryRelation($conn, $query)
class Connection implements IConnection
{
	use NotSerializable; // TODO: implement connection serialization instead of giving up

	private $name;
	private $connCtl;
	private $typeCtl;
	private $stmtExec;
	private $copyCtl;

	private $config;

	private $inTrans = false; // FIXME: rely on pg_transaction_status() instead of duplicating its logic here (it is reported not to talk to the server, so it shall be fast)

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
		$this->typeCtl = new TypeControl($this, $this->connCtl);
		$this->stmtExec = new StatementExecution($this->connCtl, $this->typeCtl);
		$this->copyCtl = new CopyControl();
		$this->config = new ConnConfig($this, $this->connCtl->requireConnection());
	}

	final public function getName()
	{
		return $this->name;
	}

	final public function getConfig()
	{
		return $this->config;
	}

	public function inTransaction()
	{
		return $this->inTrans;
	}

	public function startTransaction($transactionOptions = 0)
	{
		$connHandler = $this->connCtl->requireConnection();
		if ($this->inTrans) {
			trigger_error('A transaction is already active, cannot start a new one.', E_USER_WARNING);
			return;
		}

		// TODO: issue START TRANSACTION
		pg_query($connHandler, 'START TRANSACTION'); // TODO: handle errors
		// TODO: adjust the savepoint info
		// TODO: issue an event in case someone is listening

		$this->inTrans = true;
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
		$connHandler = $this->connCtl->requireConnection();
		if (!$this->inTrans) {
			trigger_error('No transaction is active, nothing to commit.', E_USER_WARNING);
			return;
		}

		// TODO: issue COMMIT
		pg_query($connHandler, 'COMMIT'); // TODO: handle errors
		// TODO: adjust the savepoint info
		// TODO: issue an event in case someone is listening

		$this->inTrans = false;
	}

	public function rollback()
	{
		$connHandler = $this->connCtl->requireConnection();
		if (!$this->inTrans) {
			trigger_error('No transaction is active, nothing to roll back.', E_USER_WARNING);
			return;
		}

		// TODO: issue ROLLBACK
		pg_query($connHandler, 'ROLLBACK'); // TODO: handle errors
		// TODO: adjust the savepoints info
		// TODO: issue an event in case someone is listening

		$this->inTrans = false;
	}

	private function ident($ident)
	{
		return '"' . strtr($ident, ['"' => '""']) . '"'; // FIXME: revise; move where appropriate
	}

	public function savepoint($name)
	{
		$connHandler = $this->connCtl->requireConnection();
		if (!$this->inTrans) {
			throw new InvalidStateException('No transaction is active, cannot create any savepoint.');
		}

		// TODO: issue SAVEPOINT "$name"
		pg_query($connHandler, sprintf('SAVEPOINT %s', $this->ident($name))); // TODO: handle errors
		// TODO: adjust the savepoints info
		// TODO: issue an event in case someone is listening
	}

	public function rollbackToSavepoint($name)
	{
		$connHandler = $this->connCtl->requireConnection();
		if (!$this->inTrans) {
			throw new InvalidStateException('No transaction is active, cannot roll back to any savepoint.');
		}

		// TODO: issue ROLLBACK TO SAVEPOINT "$name"
		pg_query($connHandler, sprintf('ROLLBACK TO SAVEPOINT %s', $this->ident($name))); // TODO: handle errors
		// TODO: adjust the savepoints info
		// TODO: issue an event in case someone is listening
	}

	public function releaseSavepoint($name)
	{
		$connHandler = $this->connCtl->requireConnection();
		if (!$this->inTrans) {
			throw new InvalidStateException('No transaction is active, cannot release any savepoint.');
		}

		// TODO: issue RELEASE SAVEPOINT "$name"
		pg_query($connHandler, sprintf('RELEASE SAVEPOINT %s', $this->ident($name))); // TODO: handle errors
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
		return pg_get_pid($this->connCtl->requireConnection());
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

	//region Type Control

	public function getTypeRegister()
	{
		return $this->typeCtl->getTypeRegister();
	}

	public function getTypeDictionary()
	{
		return $this->typeCtl->getTypeDictionary();
	}

	public function flushTypeDictionary()
	{
		$this->typeCtl->flushTypeDictionary();
	}

	//endregion

	//region Statement Execution

	public function rawQuery($sqlStatement)
	{
		return $this->stmtExec->rawQuery($sqlStatement);
	}

	public function rawMultiQuery($sqlStatements)
	{
		return $this->stmtExec->rawMultiQuery($sqlStatements);
	}

	public function runScript($sqlScript)
	{
		return $this->stmtExec->runScript($sqlScript);
	}

	//endregion

	//region Copy Control

	public function copyFromFile($file, $table, $columns = null, $options = [])
	{
		return $this->copyCtl->copyFromFile($file, $table, $columns, $options);
	}

	public function copyFromProgram($program, $table, $columns = null, $options = [])
	{
		return $this->copyCtl->copyFromProgram($program, $table, $columns, $options);
	}

	public function copyFromInput($table, $columns = null, $options = [])
	{
		return $this->copyCtl->copyFromInput($table, $columns, $options);
	}

	public function copyToFile($file, $tableOrQuery, $columns = null, $options = [])
	{
		return $this->copyCtl->copyToFile($file, $tableOrQuery, $columns, $options);
	}

	public function copyToProgram($program, $tableOrQuery, $columns = null, $options = [])
	{
		return $this->copyCtl->copyToProgram($program, $tableOrQuery, $columns, $options);
	}

	public function copyToArray($table, $options = [])
	{
		return $this->copyCtl->copyToArray($table, $options);
	}

	//endregion
}
