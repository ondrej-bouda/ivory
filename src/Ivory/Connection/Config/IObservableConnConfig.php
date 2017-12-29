<?php
declare(strict_types=1);
namespace Ivory\Connection\Config;

use Ivory\Value\Quantity;

/**
 * An {@link IConnConfig} extension providing measures to watch for changes of parameter values currently in effect.
 *
 * Note that observers are notified of any parameters which changed their value effective for the current context. E.g.,
 * if a parameter is changed only for a given transaction, its effective change to the original value upon transaction
 * commit or rollback is also notified about. Or, if a parameter is changed for the whole session, but inside
 * a transaction which gets rolled back later on, the change to the original value upon the rollback is notified about.
 * The same holds even for rolling back to transaction savepoints.
 *
 * Note that in some cases the observer might be called even if the parameter value actually did not change. Anytime
 * a value is set up for a configuration parameter, observers get notified about it regardless of whether the new value
 * is different from the old one.
 */
interface IObservableConnConfig extends IConnConfig
{
    /**
     * Registers a given object as observer of changes of the given or any configuration parameter.
     *
     * @param IConfigObserver $observer object which will be notified, through the
     *                                    {@link IConfigObserver::notifyPropertyChange()} method, about the changes
     * @param string|string[]|null $parameterName one or more names of properties to observe;
     *                                            either of {@link ConfigParam} constants may be used, as well as any
     *                                              customized parameter names;
     *                                            <tt>null</tt> to observe any property
     */
    function addObserver(IConfigObserver $observer, $parameterName = null): void;

    /**
     * Removes a given observer from observing any configuration parameters observed so far.
     *
     * @param IConfigObserver $observer
     */
    function removeObserver(IConfigObserver $observer): void;

    /**
     * Removes any observer registered so far from observing any configuration parameters.
     */
    function removeAllObservers(): void;

    /**
     * Tells all observers of a given property that its value has changed.
     *
     * Normally, this should be called automatically by this observable object. The method is there as a fallback.
     *
     * @param string $parameterName name of the parameter
     * @param bool|float|int|Quantity|string|null $newValue the new value; if not given, it gets queried automatically
     */
    function notifyPropertyChange(string $parameterName, $newValue = null): void;

    /**
     * Tells all observers of any property that any property might have changed its value.
     *
     * Used after invocation of the `RESET ALL` command, or after any query which might have side-effects on
     * configuration parameters.
     */
    function notifyPropertiesReset(): void;
}
