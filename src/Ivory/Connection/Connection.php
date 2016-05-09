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
	private $txCtl;
	private $ipcCtl;

	private $config;


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
		$this->connCtl = new ConnectionControl($params); // TODO: extract all usages of ConnectionControl::requireConnection() - consider introducing an interface specifying the method, named like PGSQLDriver or ConnectionManager or ConnectionPool
		$this->typeCtl = new TypeControl($this, $this->connCtl);
		$this->stmtExec = new StatementExecution($this->connCtl, $this->typeCtl);
		$this->copyCtl = new CopyControl();
		$this->txCtl = new TransactionControl($this->connCtl, $this->stmtExec);
		$this->ipcCtl = new IPCControl($this->connCtl);
		$this->config = new ConnConfig($this->connCtl, $this->stmtExec, $this->txCtl);
	}

	final public function getName()
	{
		return $this->name;
	}

	final public function getConfig()
	{
		return $this->config;
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

	public function getStatementExceptionFactory()
	{
		return $this->stmtExec->getStatementExceptionFactory();
	}

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

	//region Transaction Control

	public function inTransaction()
	{
		return $this->txCtl->inTransaction();
	}

	public function startTransaction($transactionOptions = 0)
	{
		return $this->txCtl->startTransaction($transactionOptions);
	}

	public function setupTransaction($transactionOptions)
	{
		$this->txCtl->setupTransaction($transactionOptions);
	}

	public function setupSubsequentTransactions($transactionOptions)
	{
		$this->txCtl->setupSubsequentTransactions($transactionOptions);
	}

	public function commit()
	{
		return $this->txCtl->commit();
	}

	public function rollback()
	{
		return $this->txCtl->rollback();
	}

	public function savepoint($name)
	{
		$this->txCtl->savepoint($name);
	}

	public function rollbackToSavepoint($name)
	{
		$this->txCtl->rollbackToSavepoint($name);
	}

	public function releaseSavepoint($name)
	{
		$this->txCtl->releaseSavepoint($name);
	}

	public function setTransactionSnapshot($snapshotId)
	{
		return $this->txCtl->setTransactionSnapshot($snapshotId);
	}

	public function exportTransactionSnapshot()
	{
		return $this->txCtl->exportTransactionSnapshot();
	}

	public function prepareTransaction($name)
	{
		return $this->txCtl->prepareTransaction($name);
	}

	public function commitPreparedTransaction($name)
	{
		$this->txCtl->commitPreparedTransaction($name);
	}

	public function rollbackPreparedTransaction($name)
	{
		$this->txCtl->rollbackPreparedTransaction($name);
	}

	public function listPreparedTransactions()
	{
		return $this->txCtl->listPreparedTransactions();
	}

	//endregion

	//region IPC Control

	public function getBackendPID()
	{
		return $this->ipcCtl->getBackendPID();
	}

	public function notify($channel, $payload = null)
	{
		$this->ipcCtl->notify($channel, $payload);
	}

	public function listen($channel)
	{
		$this->ipcCtl->listen($channel);
	}

	public function unlisten($channel)
	{
		$this->ipcCtl->unlisten($channel);
	}

	public function unlistenAll()
	{
		$this->ipcCtl->unlistenAll();
	}

	public function pollNotification()
	{
		return $this->ipcCtl->pollNotification();
	}

	//endregion
}
