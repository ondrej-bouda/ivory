<?php
declare(strict_types=1);
namespace Ivory\Connection;

use Ivory\Connection\Config\ConfigParam;
use Ivory\Exception\InvalidStateException;
use Ivory\Ivory;
use Ivory\Lang\Sql\Types;
use Ivory\Result\IQueryResult;

class TransactionControl implements IObservableTransactionControl
{
    private $connCtl;
    private $stmtExec;
    private $sessionCtl;
    /** @var ITransactionControlObserver[] */
    private $observers = [];


    public function __construct(ConnectionControl $connCtl, IStatementExecution $stmtExec, ISessionControl $sessionCtl)
    {
        $this->connCtl = $connCtl;
        $this->stmtExec = $stmtExec;
        $this->sessionCtl = $sessionCtl;
    }

    public function inTransaction(): bool
    {
        $connHandler = $this->connCtl->requireConnection();
        $txStat = pg_transaction_status($connHandler);
        return ($txStat == PGSQL_TRANSACTION_INTRANS || $txStat == PGSQL_TRANSACTION_INERROR);
    }

    public function startTransaction($transactionOptions = 0): ITxHandle
    {
        if ($this->inTransaction()) {
            throw new InvalidStateException('A transaction is already active, cannot start a new one.');
        }

        $txConfig = TxConfig::create($transactionOptions);
        $txSql = $txConfig->toSql();

        $command = 'START TRANSACTION';
        if (strlen($txSql) > 0) {
            $command .= ' ' . $txSql;
        }

        $this->stmtExec->rawCommand($command);
        $this->notifyTransactionStart();

        $coreFactory = Ivory::getCoreFactory();
        return $coreFactory->createTransactionHandle($this->stmtExec, $this, $this->sessionCtl);
    }

    public function setupSubsequentTransactions($transactionOptions): void
    {
        $txConfig = TxConfig::create($transactionOptions);
        $this->stmtExec->rawCommand('SET SESSION CHARACTERISTICS AS TRANSACTION ' . $txConfig->toSql());
    }

    public function getDefaultTxConfig(): TxConfig
    {
        $connConfig = $this->sessionCtl->getConfig();
        return TxConfig::createFromParams(
            $connConfig->get(ConfigParam::DEFAULT_TRANSACTION_ISOLATION),
            $connConfig->get(ConfigParam::DEFAULT_TRANSACTION_READ_ONLY),
            $connConfig->get(ConfigParam::DEFAULT_TRANSACTION_DEFERRABLE)
        );
    }

    public function commitPreparedTransaction(string $name): void
    {
        if ($this->inTransaction()) {
            throw new InvalidStateException('Cannot commit a prepared transaction while inside another transaction.');
        }

        $this->stmtExec->rawCommand('COMMIT PREPARED ' . Types::serializeString($name));
        $this->notifyPreparedTransactionCommit($name);
    }

    public function rollbackPreparedTransaction(string $name): void
    {
        if ($this->inTransaction()) {
            throw new InvalidStateException('Cannot rollback a prepared transaction while inside another transaction.');
        }

        $this->stmtExec->rawCommand('ROLLBACK PREPARED ' . Types::serializeString($name));
        $this->notifyPreparedTransactionRollback($name);
    }

    public function listPreparedTransactions(): IQueryResult
    {
        return $this->stmtExec->rawQuery('SELECT * FROM pg_catalog.pg_prepared_xacts');
    }


    //region IObservableTransactionControl

    public function addObserver(ITransactionControlObserver $observer): void
    {
        $hash = spl_object_hash($observer);
        $this->observers[$hash] = $observer;
    }

    public function removeObserver(ITransactionControlObserver $observer): void
    {
        $hash = spl_object_hash($observer);
        unset($this->observers[$hash]);
    }

    public function removeAllObservers(): void
    {
        $this->observers = [];
    }

    public function notifyTransactionStart(): void
    {
        foreach ($this->observers as $observer) {
            $observer->handleTransactionStart();
        }
    }

    public function notifyTransactionCommit(): void
    {
        foreach ($this->observers as $observer) {
            $observer->handleTransactionCommit();
        }
    }

    public function notifyTransactionRollback(): void
    {
        foreach ($this->observers as $observer) {
            $observer->handleTransactionRollback();
        }
    }

    public function notifySavepointSaved(string $name): void
    {
        foreach ($this->observers as $observer) {
            $observer->handleSavepointSaved($name);
        }
    }

    public function notifySavepointReleased(string $name): void
    {
        foreach ($this->observers as $observer) {
            $observer->handleSavepointReleased($name);
        }
    }

    public function notifyRollbackToSavepoint(string $name): void
    {
        foreach ($this->observers as $observer) {
            $observer->handleRollbackToSavepoint($name);
        }
    }

    public function notifyTransactionPrepared(string $name): void
    {
        foreach ($this->observers as $observer) {
            $observer->handleTransactionPrepared($name);
        }
    }

    public function notifyPreparedTransactionCommit(string $name): void
    {
        foreach ($this->observers as $observer) {
            $observer->handlePreparedTransactionCommit($name);
        }
    }

    public function notifyPreparedTransactionRollback(string $name): void
    {
        foreach ($this->observers as $observer) {
            $observer->handlePreparedTransactionRollback($name);
        }
    }

    //endregion
}
