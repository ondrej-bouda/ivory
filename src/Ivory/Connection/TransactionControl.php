<?php
namespace Ivory\Connection;

use Ivory\Exception\InternalException;
use Ivory\Exception\InvalidStateException;
use Ivory\Result\IQueryResult;

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
            return false;
        }

        $this->stmtExec->rawQuery('START TRANSACTION');
        // TODO: adjust the savepoint info
        // TODO: issue an event in case someone is listening

        return true;
    }

    public function setupTransaction($transactionOptions)
    {
        $this->stmtExec->rawQuery('SET TRANSACTION ' . $this->txConfigToSql($transactionOptions));
    }

    public function setupSubsequentTransactions($transactionOptions)
    {
        $this->stmtExec->rawQuery('SET SESSION CHARACTERISTICS AS TRANSACTION ' . $this->txConfigToSql($transactionOptions));
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

        $this->stmtExec->rawQuery('COMMIT');
        // TODO: adjust the savepoint info
        // TODO: issue an event in case someone is listening

        return true;
    }

    public function rollback()
    {
        if (!$this->inTransaction()) {
            trigger_error('No transaction is active, nothing to roll back.', E_USER_WARNING);
            return false;
        }

        $this->stmtExec->rawQuery('ROLLBACK');
        // TODO: adjust the savepoints info
        // TODO: issue an event in case someone is listening

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

        $this->stmtExec->rawQuery(sprintf('SAVEPOINT %s', $this->quoteIdent($name)));
        // TODO: adjust the savepoints info
        // TODO: issue an event in case someone is listening
    }

    public function rollbackToSavepoint($name)
    {
        if (!$this->inTransaction()) {
            throw new InvalidStateException('No transaction is active, cannot roll back to any savepoint.');
        }

        $this->stmtExec->rawQuery(sprintf('ROLLBACK TO SAVEPOINT %s', $this->quoteIdent($name)));
        // TODO: adjust the savepoints info
        // TODO: issue an event in case someone is listening
    }

    public function releaseSavepoint($name)
    {
        if (!$this->inTransaction()) {
            throw new InvalidStateException('No transaction is active, cannot release any savepoint.');
        }

        $this->stmtExec->rawQuery(sprintf('RELEASE SAVEPOINT %s', $this->quoteIdent($name)));
        // TODO: adjust the savepoints info
        // TODO: issue an event in case someone is listening
    }

    public function setTransactionSnapshot($snapshotId)
    {
        if (!$this->inTransaction()) {
            trigger_error('No transaction is active, cannot set the transaction snapshot.', E_USER_WARNING);
            return false;
        }

        $this->stmtExec->rawQuery(sprintf("SET TRANSACTION SNAPSHOT '%s'", $this->quoteString($snapshotId)));
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

        $this->stmtExec->rawQuery(sprintf("PREPARE TRANSACTION '%s'", $this->quoteString($name)));
        return true;
    }

    public function commitPreparedTransaction($name)
    {
        if ($this->inTransaction()) {
            throw new InvalidStateException('Cannot commit a prepared transaction while inside another transaction.');
        }

        $this->stmtExec->rawQuery(sprintf("COMMIT PREPARED '%s'", $this->quoteString($name)));
    }

    public function rollbackPreparedTransaction($name)
    {
        if ($this->inTransaction()) {
            throw new InvalidStateException('Cannot rollback a prepared transaction while inside another transaction.');
        }

        $this->stmtExec->rawQuery(sprintf("ROLLBACK PREPARED '%s'", $this->quoteString($name)));
    }

    public function listPreparedTransactions()
    {
        return $this->stmtExec->rawQuery('SELECT * FROM pg_catalog.pg_prepared_xacts');
    }
}
