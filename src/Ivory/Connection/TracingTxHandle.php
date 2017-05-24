<?php
namespace Ivory\Connection;

/**
 * {@inheritdoc}
 *
 * This implementation helps the development by keeping track where exactly the transaction was started. Then, if the
 * transaction is not closed properly before the handle gets freed up from memory, a warning is printed with the call
 * stack of where the transaction was initialized.
 */
class TracingTxHandle extends TxHandle
{
    private $backtrace;

    public function __construct(IStatementExecution $stmtExec, IObservableTransactionControl $observableTxCtl)
    {
        parent::__construct($stmtExec, $observableTxCtl);

        $this->backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    }

    public function __destruct()
    {
        parent::__destruct();

        if ($this->isOpen()) {
            error_log('The open transaction handle has been created in ' . print_r($this->backtrace, true));
        }
    }
}
