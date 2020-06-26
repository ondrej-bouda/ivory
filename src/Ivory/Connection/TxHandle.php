<?php
declare(strict_types=1);
namespace Ivory\Connection;

use Ivory\Exception\InvalidStateException;
use Ivory\Lang\Sql\Types;
use Ivory\Utils\StringUtils;

/**
 * Handle of an open transaction.
 *
 * {@inheritDoc}
 *
 * This implementation checks, upon destruction, whether the transaction has properly been closed. If not, i.e., when
 * the transaction handle gets lost and thus no further means of controlling the transaction are available, a warning is
 * emitted.
 */
class TxHandle implements ITxHandle
{
    private const PREPARED_TX_RANDOM_NAME_PREFIX = 'IvoryTx';
    /** Total length (including prefix) of random names generated for prepared transactions without an explicit name. */
    private const PREPARED_TX_RANDOM_NAME_LEN = 16;

    private $open = true;
    private $stmtExec;
    private $txCtl;
    private $sessionCtl;

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
            trigger_error($this->makeReleasedOpenHandleWarning(), E_USER_WARNING);
        }
    }

    protected function makeReleasedOpenHandleWarning(): string
    {
        return 'An open transaction handle has been released from memory. Has the transaction been closed properly?';
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
        $str = Types::serializeString($snapshotId);
        $this->stmtExec->rawCommand("SET TRANSACTION SNAPSHOT $str");
    }

    public function exportTransactionSnapshot(): string
    {
        $this->assertOpen();

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
        $this->stmtExec->rawCommand('SAVEPOINT ' . Types::serializeIdent($name));
        $this->txCtl->notifySavepointSaved($name);
    }

    public function rollbackToSavepoint(string $name): void
    {
        $this->assertOpen();
        $this->stmtExec->rawCommand('ROLLBACK TO SAVEPOINT ' . Types::serializeIdent($name));
        $this->txCtl->notifyRollbackToSavepoint($name);
    }

    public function releaseSavepoint(string $name): void
    {
        $this->assertOpen();
        $this->stmtExec->rawCommand('RELEASE SAVEPOINT ' . Types::serializeIdent($name));
        $this->txCtl->notifySavepointReleased($name);
    }

    public function prepareTransaction(?string $name = null): string
    {
        $this->assertOpen();

        if ($name === null) {
            $name = self::PREPARED_TX_RANDOM_NAME_PREFIX;
            $name .= StringUtils::randomHexString(self::PREPARED_TX_RANDOM_NAME_LEN - strlen($name));
        }

        $str = Types::serializeString($name);
        $this->stmtExec->rawCommand("PREPARE TRANSACTION $str");
        $this->open = false;
        $this->txCtl->notifyTransactionPrepared($name);

        return $name;
    }
}
