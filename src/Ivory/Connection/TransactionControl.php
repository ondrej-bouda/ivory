<?php
namespace Ivory\Connection;

use Ivory\Exception\InvalidStateException;

class TransactionControl implements ITransactionControl
{
    private $connCtl;
    private $stmtExec;


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
            return;
        }

        $this->stmtExec->rawQuery('START TRANSACTION');
        // TODO: adjust the savepoint info
        // TODO: issue an event in case someone is listening
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
        if (!$this->inTransaction()) {
            trigger_error('No transaction is active, nothing to commit.', E_USER_WARNING);
            return;
        }

        $this->stmtExec->rawQuery('COMMIT');
        // TODO: adjust the savepoint info
        // TODO: issue an event in case someone is listening
    }

    public function rollback()
    {
        if (!$this->inTransaction()) {
            trigger_error('No transaction is active, nothing to roll back.', E_USER_WARNING);
            return;
        }

        $this->stmtExec->rawQuery('ROLLBACK');
        // TODO: adjust the savepoints info
        // TODO: issue an event in case someone is listening
    }

    private function ident($ident)
    {
        return '"' . strtr($ident, ['"' => '""']) . '"'; // FIXME: revise; move where appropriate
    }

    public function savepoint($name)
    {
        if (!$this->inTransaction()) {
            throw new InvalidStateException('No transaction is active, cannot create any savepoint.');
        }

        $this->stmtExec->rawQuery(sprintf('SAVEPOINT %s', $this->ident($name)));
        // TODO: adjust the savepoints info
        // TODO: issue an event in case someone is listening
    }

    public function rollbackToSavepoint($name)
    {
        if (!$this->inTransaction()) {
            throw new InvalidStateException('No transaction is active, cannot roll back to any savepoint.');
        }

        $this->stmtExec->rawQuery(sprintf('ROLLBACK TO SAVEPOINT %s', $this->ident($name)));
        // TODO: adjust the savepoints info
        // TODO: issue an event in case someone is listening
    }

    public function releaseSavepoint($name)
    {
        if (!$this->inTransaction()) {
            throw new InvalidStateException('No transaction is active, cannot release any savepoint.');
        }

        $this->stmtExec->rawQuery(sprintf('RELEASE SAVEPOINT %s', $this->ident($name)));
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
        return null;
    }
}
