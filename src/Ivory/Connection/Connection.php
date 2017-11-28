<?php
declare(strict_types=1);

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

    public function isConnected(): ?bool
    {
        return $this->connCtl->isConnected();
    }

    public function isConnectedWait(): ?bool
    {
        return $this->connCtl->isConnectedWait();
    }

    public function connect(?\Closure $initProcedure = null): bool
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

    public function registerConnectStartHook(\Closure $closure): void
    {
        $this->connCtl->registerConnectStartHook($closure);
    }

    public function registerPreDisconnectHook(\Closure $closure): void
    {
        $this->connCtl->registerPreDisconnectHook($closure);
    }

    public function registerPostDisconnectHook(\Closure $closure): void
    {
        $this->connCtl->registerPostDisconnectHook($closure);
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

    public function flushTypeDictionary(): void
    {
        $this->typeCtl->flushTypeDictionary();
    }

    //endregion

    //region Statement Execution

    public function query($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams): IQueryResult
    {
        return $this->stmtExec->query($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams);
    }

    public function querySingleTuple($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams): ITuple
    {
        return $this->stmtExec->querySingleTuple($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams);
    }

    public function querySingleValue($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams)
    {
        return $this->stmtExec->querySingleValue($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams);
    }

    public function command($sqlFragmentPatternOrCommand, ...$fragmentsAndParams): ICommandResult
    {
        return $this->stmtExec->command($sqlFragmentPatternOrCommand, ...$fragmentsAndParams);
    }

    public function rawQuery(string $sqlQuery): IQueryResult
    {
        return $this->stmtExec->rawQuery($sqlQuery);
    }

    public function rawCommand(string $sqlCommand): ICommandResult
    {
        return $this->stmtExec->rawCommand($sqlCommand);
    }

    public function rawMultiStatement(iterable $sqlStatements): array
    {
        return $this->stmtExec->rawMultiStatement($sqlStatements);
    }

    public function runScript(string $sqlScript): array
    {
        return $this->stmtExec->runScript($sqlScript);
    }

    public function getStatementExceptionFactory(): StatementExceptionFactory
    {
        return $this->stmtExec->getStatementExceptionFactory();
    }

    //endregion

    //region Copy Control

    public function copyFromFile(string $file, string $table, ?array $columns = null, array $options = []): ICommandResult
    {
        return $this->copyCtl->copyFromFile($file, $table, $columns, $options);
    }

    public function copyFromProgram(string $program, string $table, ?array $columns = null, array $options = []): ICommandResult
    {
        return $this->copyCtl->copyFromProgram($program, $table, $columns, $options);
    }

    public function copyFromInput(string $table, ?array $columns = null, array $options = []): ICopyInResult
    {
        return $this->copyCtl->copyFromInput($table, $columns, $options);
    }

    public function copyToFile(string $file, $tableOrRelationDefinition, ?array $columns = null, array $options = []): ICommandResult
    {
        return $this->copyCtl->copyToFile($file, $tableOrRelationDefinition, $columns, $options);
    }

    public function copyToProgram(string $program, $tableOrRelationDefinition, ?array $columns = null, array $options = []): ICommandResult
    {
        return $this->copyCtl->copyToProgram($program, $tableOrRelationDefinition, $columns, $options);
    }

    public function copyToArray(string $table, array $options = []): array
    {
        return $this->copyCtl->copyToArray($table, $options);
    }

    //endregion

    //region Transaction Control

    public function inTransaction(): bool
    {
        return $this->txCtl->inTransaction();
    }

    public function startTransaction($transactionOptions = 0): ITxHandle
    {
        return $this->txCtl->startTransaction($transactionOptions);
    }

    public function setupSubsequentTransactions($transactionOptions): void
    {
        $this->txCtl->setupSubsequentTransactions($transactionOptions);
    }

    public function commitPreparedTransaction(string $name): void
    {
        $this->txCtl->commitPreparedTransaction($name);
    }

    public function rollbackPreparedTransaction(string $name): void
    {
        $this->txCtl->rollbackPreparedTransaction($name);
    }

    public function listPreparedTransactions(): IQueryResult
    {
        return $this->txCtl->listPreparedTransactions();
    }

    //endregion

    //region Transaction observing

    public function addTransactionControlObserver(ITransactionControlObserver $observer): void
    {
        $this->txCtl->addObserver($observer);
    }

    public function removeTransactionControlObserver(ITransactionControlObserver $observer): void
    {
        $this->txCtl->removeObserver($observer);
    }

    public function removeAllTransactionControlObservers(): void
    {
        $this->txCtl->removeAllObservers();
    }

    //endregion

    //region IPC Control

    public function getBackendPID(): int
    {
        return $this->ipcCtl->getBackendPID();
    }

    public function notify(string $channel, ?string $payload = null): void
    {
        $this->ipcCtl->notify($channel, $payload);
    }

    public function listen(string $channel): void
    {
        $this->ipcCtl->listen($channel);
    }

    public function unlisten(string $channel): void
    {
        $this->ipcCtl->unlisten($channel);
    }

    public function unlistenAll(): void
    {
        $this->ipcCtl->unlistenAll();
    }

    public function pollNotification(): ?Notification
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
