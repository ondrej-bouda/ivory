<?php
/** @noinspection PhpInappropriateInheritDocUsageInspection PhpStorm bug WI-54015 */
declare(strict_types=1);
namespace Ivory\Connection;

/**
 * Handle of an open transaction, rolling back the transaction automatically upon destruction if still open.
 *
 * {@inheritDoc}
 *
 * @see ITransactionControl::startAutoTransaction()
 */
class AutoTxHandle extends TxHandle
{
    public function __destruct()
    {
        $this->rollbackIfOpen();
        parent::__destruct();
    }
}
