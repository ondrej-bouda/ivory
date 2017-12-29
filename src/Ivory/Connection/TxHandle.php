<?php
declare(strict_types=1);
namespace Ivory\Connection;

use Ivory\Exception\InvalidStateException;
use Ivory\Result\IQueryResult;
use Ivory\Type\IValueSerializer;
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
    private $sessionCtl;
    private $identSerializer = null;
    private $stringSerializer = null;

    public function __construct(
        IStatementExecution $stmtExec,
        IObservableTransactionControl $observableTxCtl,
        ISessionControl $sessionCtl
    ) {
        $this->stmtExec = $stmtExec;
        $this->txCtl = $observableTxCtl;
        $this->sessionCtl = $sessionCtl;
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

    private function ensureIdentSerializer(): IValueSerializer
    {
        if ($this->identSerializer === null) {
            $this->identSerializer = new IdentifierSerializer();
        }
        return $this->identSerializer;
    }

    private function ensureStringSerializer(): IValueSerializer
    {
        if ($this->stringSerializer === null) {
            $this->stringSerializer = new StringType('pg_catalog', 'text');
        }
        return $this->stringSerializer;
    }

    private function assertOpen(): void
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

    public function getTxConfig(): TxConfig
    {
        $this->assertOpen();
        $connConfig = $this->sessionCtl->getConfig();
        return TxConfig::createFromParams(
            $connConfig->get('transaction_isolation'),
            $connConfig->get('transaction_read_only'),
            $connConfig->get('transaction_deferrable')
        );
    }

    public function setupTransaction($transactionOptions): void
    {
        $this->assertOpen();
        $txConfig = TxConfig::create($transactionOptions);
        $this->stmtExec->rawCommand('SET TRANSACTION ' . $txConfig->toSql());
    }

    public function setTransactionSnapshot(string $snapshotId): void
    {
        $this->assertOpen();
        $str = $this->ensureStringSerializer()->serializeValue($snapshotId);
        $this->stmtExec->rawCommand("SET TRANSACTION SNAPSHOT $str");
    }

    public function exportTransactionSnapshot(): string
    {
        $this->assertOpen();

        /** @var IQueryResult $r */
        $r = $this->stmtExec->rawQuery('SELECT pg_export_snapshot()');
        return $r->value();
    }

    public function commit(): void
    {
        $this->assertOpen();
        $this->stmtExec->rawCommand('COMMIT');
        $this->open = false;
        $this->txCtl->notifyTransactionCommit();
    }

    public function rollback(): void
    {
        $this->assertOpen();
        $this->stmtExec->rawCommand('ROLLBACK');
        $this->open = false;
        $this->txCtl->notifyTransactionRollback();
    }

    public function rollbackIfOpen(): void
    {
        if ($this->open) {
            $this->stmtExec->rawCommand('ROLLBACK');
            $this->open = false;
            $this->txCtl->notifyTransactionRollback();
        }
    }

    public function savepoint(string $name): void
    {
        $this->assertOpen();
        $ident = $this->ensureIdentSerializer()->serializeValue($name);
        $this->stmtExec->rawCommand("SAVEPOINT $ident");
        $this->txCtl->notifySavepointSaved($name);
    }

    public function rollbackToSavepoint(string $name): void
    {
        $this->assertOpen();
        $ident = $this->ensureIdentSerializer()->serializeValue($name);
        $this->stmtExec->rawCommand("ROLLBACK TO SAVEPOINT $ident");
        $this->txCtl->notifyRollbackToSavepoint($name);
    }

    public function releaseSavepoint(string $name): void
    {
        $this->assertOpen();
        $ident = $this->ensureIdentSerializer()->serializeValue($name);
        $this->stmtExec->rawCommand("RELEASE SAVEPOINT $ident");
        $this->txCtl->notifySavepointReleased($name);
    }

    public function prepareTransaction(?string $name = null): string
    {
        $this->assertOpen();

        if ($name === null) {
            $len = 16;
            $bytes = random_bytes(ceil($len / 2));
            $name = substr(bin2hex($bytes), 0, $len);
        }

        $str = $this->ensureStringSerializer()->serializeValue($name);
        $this->stmtExec->rawCommand("PREPARE TRANSACTION $str");
        $this->open = false;
        $this->txCtl->notifyTransactionPrepared($name);

        return $name;
    }
}
