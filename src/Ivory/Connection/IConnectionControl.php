<?php
namespace Ivory\Connection;

use Ivory\Exception\ConnectionException;

interface IConnectionControl
{
    /**
     * @return ConnectionParameters parameters for establishing the connection
     */
    function getParameters(): ConnectionParameters;

    /**
     * Finds out whether the connection is established in the moment.
     *
     * @return bool|null <tt>true</tt> if the connection is established,
     *                   <tt>null</tt> if no connection was requested to be established (yet),
     *                   <tt>false</tt> if the connection was tried to be established, but it is broken or still in
     *                     process of (asynchronous) connecting (use {@link isConnectedWait()} instead to distinguish
     *                     the latter)
     */
    function isConnected(): ?bool;

    /**
     * Finds out whether the connection is established. Waits for asynchronous connecting process to finish before
     * figuring out.
     *
     * @return bool|null <tt>true</tt> if the connection is established,
     *                   <tt>null</tt> if no connection was requested to be established (yet),
     *                   <tt>false</tt> if the connection is broken
     */
    function isConnectedWait(): ?bool;

    /**
     * Starts establishing a connection with the database according to the current connection parameters, if it has not
     * been established yet for this connection object.
     *
     * An asynchronous connection is used - the method merely starts connecting to the database and returns immediately.
     * Further operations on this connection, which really need the database, block until the connection is actually
     * established.
     *
     * If the connection has already been established, or started to being established, nothing is done and
     * `false` is returned.
     *
     * For a synchronous variant, see {@link connectWait()}.
     *
     * The connection is not shared with any other `IConnection` objects. A new connection is always established.
     *
     * @internal Ivory design note: Asynchronous connection mode is the default used by Ivory. The method does not even
     * reflect that the connection is asynchronous, so that it is clear that no extra work is needed. Indeed, the fact
     * that the connection is asynchronous is transparent to the caller.
     *
     * @param \Closure|null $initProcedure code to execute after the connection is established, before any queries;
     *                                     the closure is given one {@link IConnection} argument - this connection;
     *                                     suitable for, e.g., setting up <tt>search_path</tt>, session variables, etc.
     * @return bool <tt>true</tt> if the connection has actually started to being established,
     *              <tt>false</tt> if the connection has already been open or started opening and thus this was a no-op
     * @throws ConnectionException on error connecting to the database
     */
    function connect(?\Closure $initProcedure = null): bool;

    /**
     * Establishes a connection with the database and waits for the connection to be established.
     *
     * The operation is almost the same as {@link connect()}, except this method does not return until the connection is
     * actually established. The current connection parameters are used for the connection.
     *
     * If the connection has already been established, nothing is done and `false` is returned.
     *
     * If the connection has been started asynchronously using {@link connect()} before, this method merely waits until
     * the connection is ready, and returns `false` then.
     *
     * The connection is not shared with any other {@link IConnection} objects. A new connection is always established.
     *
     * @return bool <tt>true</tt> if a new connection has just been opened,
     *              <tt>false</tt> if the connection has already been open and thus this was a no-op
     * @throws ConnectionException on error connecting to the database
     */
    function connectWait(): bool;

    /**
     * Closes the connection (if any).
     *
     * Note that an uncommitted transaction (if any) will get rolled back by PostgreSQL.
     *
     * After disconnecting, it is possible to connect again using {@link connect()} or {@link connectWait()}.
     *
     * @return bool <tt>true</tt> if the connection has actually been closed,
     *              <tt>false</tt> if no connection was established and thus this was a no-op
     * @throws ConnectionException on error closing the connection
     */
    function disconnect(): bool;

    /**
     * Registers a closure to be called right after starting to connect asynchronously to the database.
     *
     * Multiple hooks may be registered. They will be called in the registration order.
     *
     * Note that the hooks are only called if the {@link connect()} method is used. If connecting synchronously using
     * {@link connectWait()}, the hooks are *not* called.
     *
     * @param \Closure $closure closure to call, given no arguments
     */
    function registerConnectStartHook(\Closure $closure): void;

    /**
     * Registers a closure to be called right before disconnecting from the database.
     *
     * Multiple hooks may be registered. They will be called in the registration order.
     *
     * The connection control disconnects from the database either on an explicit call of the {@link disconnect()}
     * method, or when the connection gets destroyed before freeing from memory.
     *
     * @param \Closure $closure closure to call, given no arguments
     */
    function registerPreDisconnectHook(\Closure $closure): void;

    /**
     * Registers a closure to be called right after disconnecting from the database.
     *
     * Multiple hooks may be registered. They will be called in the registration order.
     *
     * The connection control disconnects from the database either on an explicit call of the {@link disconnect()}
     * method, or when the connection gets destroyed before freeing from memory.
     *
     * @param \Closure $closure closure to call, given no arguments
     */
    function registerPostDisconnectHook(\Closure $closure): void;
}
