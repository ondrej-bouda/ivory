<?php
declare(strict_types=1);
namespace Ivory\Connection;

/**
 * Handle of an open transaction, tracing the point in source code where the transaction was opened.
 *
 * {@inheritDoc}
 *
 * This implementation helps the development by keeping track where exactly the transaction was started. Then, if the
 * transaction is not closed properly before the handle gets freed up from memory, a warning is printed with the call
 * stack of where the transaction was initialized.
 */
class TracingTxHandle extends TxHandle
{
    private $backtrace;

    public function __construct(
        IStatementExecution $stmtExec,
        IObservableTransactionControl $observableTxCtl,
        ISessionControl $sessionCtl
    ) {
        parent::__construct($stmtExec, $observableTxCtl, $sessionCtl);

        $this->backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    }

    protected function makeReleasedOpenHandleWarning(): string
    {
        return
            parent::makeReleasedOpenHandleWarning() .
            ' The handle has been created in ' .
            print_r($this->backtrace, true);
    }
}
