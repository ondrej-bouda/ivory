<?php
declare(strict_types=1);

namespace Ivory\Connection;

/**
 * Base implementation of a transaction control observer. Implements all handlers as empty methods.
 *
 * Intended for extension by transaction control observers which are only interested in few events.
 *
 * Recommended as the base for any {@link ITransactionControlObserver} implementations for future compatibility.
 */
class TransactionControlObserverBase implements ITransactionControlObserver
{
    use TransactionControlObserverBaseTrait;
}
