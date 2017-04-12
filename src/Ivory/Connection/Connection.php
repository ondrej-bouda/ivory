<?php
namespace Ivory\Connection;

use Ivory\Connection\Config\ConnConfig;
use Ivory\Connection\Config\IConnConfig;
use Ivory\Exception\StatementExceptionFactory;
use Ivory\Ivory;
use Ivory\Relation\ITuple;
use Ivory\Result\ICommandResult;
use Ivory\Result\ICopyInResult;
use Ivory\Result\IQueryResult;
use Ivory\Type\ITypeDictionary;
use Ivory\Type\TypeRegister;
use Ivory\Utils\NotSerializable;

// TODO: consider renaming to Database or Session or... - to reflect it is mere a facade, the single entry point
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
    private $cacheCtl;

    private $config;


    /**
     * @param string $name name for the connection
     * @param ConnectionParameters|array|string $params either a connection parameters object, or an associative array
     *                                                    of parameters for {@link ConnectionParameters::__construct()},
     *                                                    or a URL for {@link ConnectionParameters::fromUrl()},
     *                                                    or a PostgreSQL connection string (see {@link pg_connect()})
     */
    public function __construct(string $name, $params)
    {
        $this->name = $name;
        $this->connCtl = new ConnectionControl($this, $params); // TODO: extract all usages of ConnectionControl::requireConnection() - consider introducing an interface specifying the method, named like PGSQLDriver or ConnectionManager or ConnectionPool
        $this->typeCtl = new TypeControl($this, $this->connCtl);
        $this->stmtExec = new StatementExecution($this->connCtl, $this->typeCtl);
        $this->copyCtl = new CopyControl();
        $this->txCtl = new TransactionControl($this->connCtl, $this->stmtExec);
        $this->ipcCtl = new IPCControl($this->connCtl);
        $this->config = new ConnConfig($this->connCtl, $this->stmtExec, $this->txCtl);
        $this->cacheCtl = Ivory::getCoreFactory()->createCacheControl($this); // TODO: consider moving cacheCtl initialization out of Connection itself (let the core factory set it up), or do not hold cache control here but rather besides the connection register at Ivory
    }

    final public function getName(): string
    {
        return $this->name;
    }

    final public function getConfig(): IConnConfig
    {
        return $this->config;
    }


    //region Connection Control

    public function getParameters(): ConnectionParameters
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

    public function connect(\Closure $initProcedure = null): bool
    {
        return $this->connCtl->connect($initProcedure);
    }

    public function connectWait(): bool
    {
        return $this->connCtl->connectWait();
    }

    public function disconnect(): bool
    {
        return $this->connCtl->disconnect();
    }

    //endregion

    //region Type Control

    public function getTypeRegister(): TypeRegister
    {
        return $this->typeCtl->getTypeRegister();
    }

    public function getTypeDictionary(): ITypeDictionary
    {
        return $this->typeCtl->getTypeDictionary();
    }

    public function flushTypeDictionary()
    {
        $this->typeCtl->flushTypeDictionary();
    }

    //endregion

    //region Statement Execution

    public function query($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams): IQueryResult
    {
        return $this->stmtExec->query($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams);
    }

    public function querySingleTuple($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams): ITuple
    {
        return $this->stmtExec->querySingleTuple($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams);
    }

    public function querySingleValue($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams)
    {
        return $this->stmtExec->querySingleValue($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams);
    }

    public function command($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams): ICommandResult
    {
        return $this->stmtExec->command($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams);
    }

    public function rawQuery(string $sqlQuery): IQueryResult
    {
        return $this->stmtExec->rawQuery($sqlQuery);
    }

    public function rawCommand(string $sqlCommand): ICommandResult
    {
        return $this->stmtExec->rawCommand($sqlCommand);
    }

    public function rawMultiStatement($sqlStatements)
    {
        return $this->stmtExec->rawMultiStatement($sqlStatements);
    }

    public function runScript(string $sqlScript)
    {
        return $this->stmtExec->runScript($sqlScript);
    }

    public function getStatementExceptionFactory(): StatementExceptionFactory
    {
        return $this->stmtExec->getStatementExceptionFactory();
    }

    //endregion

    //region Copy Control

    public function copyFromFile(string $file, string $table, $columns = null, $options = []): ICommandResult
    {
        return $this->copyCtl->copyFromFile($file, $table, $columns, $options);
    }

    public function copyFromProgram(string $program, string $table, $columns = null, $options = []): ICommandResult
    {
        return $this->copyCtl->copyFromProgram($program, $table, $columns, $options);
    }

    public function copyFromInput(string $table, $columns = null, $options = []): ICopyInResult
    {
        return $this->copyCtl->copyFromInput($table, $columns, $options);
    }

    public function copyToFile(string $file, $tableOrRecipe, $columns = null, $options = []): ICommandResult
    {
        return $this->copyCtl->copyToFile($file, $tableOrRecipe, $columns, $options);
    }

    public function copyToProgram(string $program, $tableOrRecipe, $columns = null, $options = []): ICommandResult
    {
        return $this->copyCtl->copyToProgram($program, $tableOrRecipe, $columns, $options);
    }

    public function copyToArray(string $table, $options = [])
    {
        return $this->copyCtl->copyToArray($table, $options);
    }

    //endregion

    //region Transaction Control

    public function inTransaction(): bool
    {
        return $this->txCtl->inTransaction();
    }

    public function startTransaction($transactionOptions = 0): bool
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

    public function commit(): bool
    {
        return $this->txCtl->commit();
    }

    public function rollback(): bool
    {
        return $this->txCtl->rollback();
    }

    public function savepoint(string $name)
    {
        $this->txCtl->savepoint($name);
    }

    public function rollbackToSavepoint(string $name)
    {
        $this->txCtl->rollbackToSavepoint($name);
    }

    public function releaseSavepoint(string $name)
    {
        $this->txCtl->releaseSavepoint($name);
    }

    public function setTransactionSnapshot(string $snapshotId): bool
    {
        return $this->txCtl->setTransactionSnapshot($snapshotId);
    }

    public function exportTransactionSnapshot()
    {
        return $this->txCtl->exportTransactionSnapshot();
    }

    public function prepareTransaction(string $name): bool
    {
        return $this->txCtl->prepareTransaction($name);
    }

    public function commitPreparedTransaction(string $name)
    {
        $this->txCtl->commitPreparedTransaction($name);
    }

    public function rollbackPreparedTransaction(string $name)
    {
        $this->txCtl->rollbackPreparedTransaction($name);
    }

    public function listPreparedTransactions(): IQueryResult
    {
        return $this->txCtl->listPreparedTransactions();
    }

    //endregion

    //region IPC Control

    public function getBackendPID(): int
    {
        return $this->ipcCtl->getBackendPID();
    }

    public function notify(string $channel, string $payload = null)
    {
        $this->ipcCtl->notify($channel, $payload);
    }

    public function listen(string $channel)
    {
        $this->ipcCtl->listen($channel);
    }

    public function unlisten(string $channel)
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

    //region Cache Control

    public function isCacheEnabled(): bool
    {
        return $this->cacheCtl->isCacheEnabled();
    }

    public function cachePermanently(string $cacheKey, $object): bool
    {
        return $this->cacheCtl->cachePermanently($cacheKey, $object);
    }

    public function getCached(string $cacheKey)
    {
        return $this->cacheCtl->getCached($cacheKey);
    }

    public function flushCache(string $cacheKey): bool
    {
        return $this->cacheCtl->flushCache($cacheKey);
    }

    //endregion
}
