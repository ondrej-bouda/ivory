<?php
declare(strict_types=1);
namespace Ivory\Connection;

/**
 * An {@link ITransactionControl} extension providing measures to watch for transaction control operations.
 *
 * Note that only effective operations are notified about. E.g., when already inside a transaction, another (redundant)
 * call to start a transaction will not trigger notification to observers.
 */
interface IObservableTransactionControl extends ITransactionControl
{
    /**
     * Registers a new observer to receive notifications on transaction control operations.
     *
     * If the given observer is already observing this object, it is not added for the second time.
     *
     * @param ITransactionControlObserver $observer
     */
    function addObserver(ITransactionControlObserver $observer): void;

    /**
     * Removes a previously registered observer.
     *
     * If the given observer was not registered before, this is a no-op.
     *
     * @param ITransactionControlObserver $observer
     */
    function removeObserver(ITransactionControlObserver $observer): void;

    /**
     * Removes any observer registered so far.
     */
    function removeAllObservers(): void;


    function notifyTransactionStart(): void;

    function notifyTransactionCommit(): void;

    function notifyTransactionRollback(): void;

    /**
     * @param string $name name of the savepoint to be notified as saved
     */
    function notifySavepointSaved(string $name): void;

    /**
     * @param string $name name of the savepoint to be notified as released
     */
    function notifySavepointReleased(string $name): void;

    /**
     * @param string $name name of the savepoint to be notified as rolled back
     */
    function notifyRollbackToSavepoint(string $name): void;

    /**
     * @param string $name name of the transaction to be notified as prepared
     */
    function notifyTransactionPrepared(string $name): void;

    /**
     * @param string $name name of the prepared transaction to be notified as committed
     */
    function notifyPreparedTransactionCommit(string $name): void;

    /**
     * @param string $name name of the prepared transaction to be notified as rolled back
     */
    function notifyPreparedTransactionRollback(string $name): void;
}
