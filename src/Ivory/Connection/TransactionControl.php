<?php
namespace Ivory\Connection;

use Ivory\Exception\InternalException;
use Ivory\Exception\InvalidStateException;
use Ivory\Result\IQueryResult;

class TransactionControl implements IObservableTransactionControl
{
    private $connCtl;
    private $stmtExec;
    /** @var ITransactionControlObserver[] */
    private $observers = [];


    public function __construct(ConnectionControl $connCtl, IStatementExecution $stmtExec)
    {
        $this->connCtl = $connCtl;
        $this->stmtExec = $stmtExec;
    }

    public function inTransaction()
    {
        $connHandler = $this->connCtl->requireConnection();
        $txStat = pg_transaction_status($connHandler);
        return ($txStat == PGSQL_TRANSACTION_INTRANS || $txStat == PGSQL_TRANSACTION_INERROR);
    }

    public function startTransaction($transactionOptions = 0)
    {
        if ($this->inTransaction()) {
            trigger_error('A transaction is already active, cannot start a new one.', E_USER_WARNING);
            return false;
        }

        $this->stmtExec->rawCommand('START TRANSACTION');
        $this->notifyTransactionStart();
        return true;
    }

    public function setupTransaction($transactionOptions)
    {
        $this->stmtExec->rawCommand('SET TRANSACTION ' . $this->txConfigToSql($transactionOptions));
    }

    public function setupSubsequentTransactions($transactionOptions)
    {
        $this->stmtExec->rawCommand('SET SESSION CHARACTERISTICS AS TRANSACTION ' . $this->txConfigToSql($transactionOptions));
    }

    private function txConfigToSql($transactionOptions)
    {
        $opts = ($transactionOptions instanceof TxConfig ? $transactionOptions : new TxConfig($transactionOptions));

        $clauses = [];

        $il = $opts->getIsolationLevel();
        if ($il !== null) {
            switch ($il) {
                case TxConfig::ISOLATION_SERIALIZABLE:
                    $clauses[] = 'ISOLATION LEVEL SERIALIZABLE';
                    break;
                case TxConfig::ISOLATION_REPEATABLE_READ:
                    $clauses[] = 'ISOLATION LEVEL REPEATABLE READ';
                    break;
                case TxConfig::ISOLATION_READ_COMMITTED:
                    $clauses[] = 'ISOLATION LEVEL READ COMMITTED';
                    break;
                case TxConfig::ISOLATION_READ_UNCOMMITTED:
                    $clauses[] = 'ISOLATION LEVEL READ UNCOMMITTED';
                    break;
                default:
                    throw new InternalException('Undefined isolation level');
            }
        }

        $ro = $opts->isReadOnly();
        if ($ro !== null) {
            $clauses[] = ($ro ? 'READ ONLY' : 'READ WRITE');
        }

        $d = $opts->isDeferrable();
        if ($d !== null) {
            $clauses[] = ($d ? 'DEFERRABLE' : 'NOT DEFERRABLE');
        }

        return implode(' ', $clauses);
    }

    public function commit()
    {
        if (!$this->inTransaction()) {
            trigger_error('No transaction is active, nothing to commit.', E_USER_WARNING);
            return false;
        }

        $this->stmtExec->rawCommand('COMMIT');
        $this->notifyTransactionCommit();
        return true;
    }

    public function rollback()
    {
        if (!$this->inTransaction()) {
            trigger_error('No transaction is active, nothing to roll back.', E_USER_WARNING);
            return false;
        }

        $this->stmtExec->rawCommand('ROLLBACK');
        $this->notifyTransactionRollback();
        return true;
    }

    private function quoteIdent($ident) // FIXME: revise; move where appropriate
    {
        return '"' . strtr($ident, ['"' => '""']) . '"';
    }

    private function quoteString($str) // FIXME: revise; move where appropriate
    {
        return "'" . pg_escape_string($this->connCtl->requireConnection(), $str) . "'";
    }

    public function savepoint($name)
    {
        if (!$this->inTransaction()) {
            throw new InvalidStateException('No transaction is active, cannot create any savepoint.');
        }

        $this->stmtExec->rawCommand(sprintf('SAVEPOINT %s', $this->quoteIdent($name)));
        $this->notifySavepointSaved($name);
    }

    public function rollbackToSavepoint($name)
    {
        if (!$this->inTransaction()) {
            throw new InvalidStateException('No transaction is active, cannot roll back to any savepoint.');
        }

        $this->stmtExec->rawCommand(sprintf('ROLLBACK TO SAVEPOINT %s', $this->quoteIdent($name)));
        $this->notifyRollbackToSavepoint($name);
    }

    public function releaseSavepoint($name)
    {
        if (!$this->inTransaction()) {
            throw new InvalidStateException('No transaction is active, cannot release any savepoint.');
        }

        $this->stmtExec->rawCommand(sprintf('RELEASE SAVEPOINT %s', $this->quoteIdent($name)));
        $this->notifySavepointReleased($name);
    }

    public function setTransactionSnapshot($snapshotId)
    {
        if (!$this->inTransaction()) {
            trigger_error('No transaction is active, cannot set the transaction snapshot.', E_USER_WARNING);
            return false;
        }

        $this->stmtExec->rawCommand("SET TRANSACTION SNAPSHOT {$this->quoteString($snapshotId)}");
        return true;
    }

    public function exportTransactionSnapshot()
    {
        if (!$this->inTransaction()) {
            trigger_error('No transaction is active, cannot export the snapshot.', E_USER_WARNING);
            return null;
        }

        /** @var IQueryResult $r */
        $r = $this->stmtExec->rawQuery('SELECT pg_export_snapshot()');
        return $r->value();
    }

    public function prepareTransaction($name)
    {
        if (!$this->inTransaction()) {
            trigger_error('No transaction is active, nothing to prepare.', E_USER_WARNING);
            return false;
        }

        $this->stmtExec->rawCommand("PREPARE TRANSACTION {$this->quoteString($name)}");
        $this->notifyTransactionPrepared($name);
        return true;
    }

    public function commitPreparedTransaction($name)
    {
        if ($this->inTransaction()) {
            throw new InvalidStateException('Cannot commit a prepared transaction while inside another transaction.');
        }

        $this->stmtExec->rawCommand("COMMIT PREPARED {$this->quoteString($name)}");
        $this->notifyPreparedTransactionCommit($name);
    }

    public function rollbackPreparedTransaction($name)
    {
        if ($this->inTransaction()) {
            throw new InvalidStateException('Cannot rollback a prepared transaction while inside another transaction.');
        }

        $this->stmtExec->rawCommand("ROLLBACK PREPARED {$this->quoteString($name)}");
        $this->notifyPreparedTransactionRollback($name);
    }

    public function listPreparedTransactions()
    {
        return $this->stmtExec->rawQuery('SELECT * FROM pg_catalog.pg_prepared_xacts');
    }


    //region IObservableTransactionControl

    public function addObserver(ITransactionControlObserver $observer)
    {
        $hash = spl_object_hash($observer);
        $this->observers[$hash] = $observer;
    }

    public function removeObserver(ITransactionControlObserver $observer)
    {
        $hash = spl_object_hash($observer);
        unset($this->observers[$hash]);
    }

    public function removeAllObservers()
    {
        $this->observers = [];
    }

    public function notifyTransactionStart()
    {
        foreach ($this->observers as $observer) {
            $observer->handleTransactionStart();
        }
    }

    public function notifyTransactionCommit()
    {
        foreach ($this->observers as $observer) {
            $observer->handleTransactionCommit();
        }
    }

    public function notifyTransactionRollback()
    {
        foreach ($this->observers as $observer) {
            $observer->handleTransactionRollback();
        }
    }

    public function notifySavepointSaved($name)
    {
        foreach ($this->observers as $observer) {
            $observer->handleSavepointSaved($name);
        }
    }

    public function notifySavepointReleased($name)
    {
        foreach ($this->observers as $observer) {
            $observer->handleSavepointReleased($name);
        }
    }

    public function notifyRollbackToSavepoint($name)
    {
        foreach ($this->observers as $observer) {
            $observer->handleRollbackToSavepoint($name);
        }
    }

    public function notifyTransactionPrepared($name)
    {
        foreach ($this->observers as $observer) {
            $observer->handleTransactionPrepared($name);
        }
    }

    public function notifyPreparedTransactionCommit($name)
    {
        foreach ($this->observers as $observer) {
            $observer->handlePreparedTransactionCommit($name);
        }
    }

    public function notifyPreparedTransactionRollback($name)
    {
        foreach ($this->observers as $observer) {
            $observer->handlePreparedTransactionRollback($name);
        }
    }

    //endregion
}
