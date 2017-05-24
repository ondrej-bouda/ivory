<?php
namespace Ivory\Connection;

use Ivory\Exception\InvalidStateException;
use Ivory\Result\IQueryResult;
use Ivory\Type\Ivory\IdentifierSerializer;
use Ivory\Type\Std\StringType;

/**
 * {@inheritdoc}
 *
 * This implementation checks, upon destruction, whether the transaction has properly been closed. If not, i.e., when
 * the transaction handle gets lost and thus no further means of controlling the transaction are available, a warning is
 * emitted.
 */
class TxHandle implements ITxHandle
{
    private $open = true;
    private $stmtExec;
    private $txCtl;
    private $identSerializer;
    private $stringSerializer;

    public function __construct(IStatementExecution $stmtExec, IObservableTransactionControl $observableTxCtl)
    {
        $this->stmtExec = $stmtExec;
        $this->txCtl = $observableTxCtl;
        $this->identSerializer = new IdentifierSerializer();
        $this->stringSerializer = new StringType('pg_catalog', 'text');
    }

    public function __destruct()
    {
        if ($this->open) {
            trigger_error(
                'An open transaction handle has been released from memory. The transaction has probably stayed open.',
                E_USER_WARNING
            );
        }
    }

    private function assertOpen()
    {
        if (!$this->open) {
            if ($this->txCtl->inTransaction()) {
                throw new InvalidStateException(
                    'Controlling the transaction using a wrong handle - this has already been closed'
                );
            } else {
                throw new InvalidStateException('The transaction is not open anymore');
            }
        }
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    public function setupTransaction($transactionOptions)
    {
        $this->assertOpen();
        $txConfig = TxConfig::create($transactionOptions);
        $this->stmtExec->rawCommand('SET TRANSACTION ' . $txConfig->toSql());
    }

    public function setTransactionSnapshot(string $snapshotId)
    {
        $this->assertOpen();
        $this->stmtExec->rawCommand("SET TRANSACTION SNAPSHOT {$this->stringSerializer->serializeValue($snapshotId)}");
        return true;
    }

    public function exportTransactionSnapshot(): string
    {
        $this->assertOpen();

        /** @var IQueryResult $r */
        $r = $this->stmtExec->rawQuery('SELECT pg_export_snapshot()');
        return $r->value();
    }

    public function commit()
    {
        $this->assertOpen();
        $this->stmtExec->rawCommand('COMMIT');
        $this->open = false;
        $this->txCtl->notifyTransactionCommit();
    }

    public function rollback()
    {
        $this->assertOpen();
        $this->stmtExec->rawCommand('ROLLBACK');
        $this->open = false;
        $this->txCtl->notifyTransactionRollback();
    }

    public function rollbackIfOpen()
    {
        if ($this->open) {
            $this->stmtExec->rawCommand('ROLLBACK');
            $this->open = false;
            $this->txCtl->notifyTransactionRollback();
        }
    }

    public function savepoint(string $name)
    {
        $this->assertOpen();
        $this->stmtExec->rawCommand(sprintf('SAVEPOINT %s', $this->identSerializer->serializeValue($name)));
        $this->txCtl->notifySavepointSaved($name);
    }

    public function rollbackToSavepoint(string $name)
    {
        $this->assertOpen();
        $this->stmtExec->rawCommand(sprintf('ROLLBACK TO SAVEPOINT %s', $this->identSerializer->serializeValue($name)));
        $this->txCtl->notifyRollbackToSavepoint($name);
    }

    public function releaseSavepoint(string $name)
    {
        $this->assertOpen();
        $this->stmtExec->rawCommand(sprintf('RELEASE SAVEPOINT %s', $this->identSerializer->serializeValue($name)));
        $this->txCtl->notifySavepointReleased($name);
    }

    public function prepareTransaction(string $name)
    {
        $this->assertOpen();
        $this->stmtExec->rawCommand("PREPARE TRANSACTION {$this->stringSerializer->serializeValue($name)}");
        $this->open = false;
        $this->txCtl->notifyTransactionPrepared($name);
    }
}
