<?php
declare(strict_types=1);
namespace Ivory\Connection;

use Ivory\Exception\ConnectionException;
use Ivory\Exception\InvalidStateException;

class ConnectionControl implements IConnectionControl
{
    /** @var IConnection */
    private $ivoryConnection;
    /** @var ConnectionParameters parameters used for connecting to the database */
    private $params;
    /** @var resource|null the connection handler, or null if connection was not requested or already closed */
    private $handler = null;
    /** @var bool whether the asynchronous connecting was finished (<tt>true</tt> if synchronous connecting was used) */
    private $finishedConnecting;
    /** @var \Closure */
    private $initProcedure = null;
    /** @var string|null last notice received on this connection */
    private $lastNotice = null;

    /** @var \Closure[] */
    private $connectStartHooks = [];
    /** @var \Closure[] */
    private $preDisconnectHooks = [];
    /** @var \Closure[] */
    private $postDisconnectHooks = [];


    public function __construct(IConnection $ivoryConnection, $params)
    {
        $this->ivoryConnection = $ivoryConnection;
        $this->params = ConnectionParameters::create($params);
    }

    public function __destruct()
    {
        $this->disconnect();
    }


    final public function getParameters(): ConnectionParameters
    {
        return $this->params;
    }

    public function isConnected(): ?bool
    {
        if ($this->handler === null) {
            return null;
        } else {
            return (pg_connection_status($this->handler) === PGSQL_CONNECTION_OK);
        }
    }

    public function isConnectedWait(): ?bool
    {
        if ($this->handler === null) {
            return null;
        } else {
            $this->waitForConnection();
            return (pg_connection_status($this->handler) === PGSQL_CONNECTION_OK);
        }
    }

    public function connect(?\Closure $initProcedure = null): bool
    {
        if ($this->handler === null) {
            $this->initProcedure = $initProcedure;
            $this->openConnection(PGSQL_CONNECT_ASYNC);
            foreach ($this->connectStartHooks as $hook) {
                call_user_func($hook);
            }
            return true;
        } else {
            return false;
        }
    }

    public function connectWait(): bool
    {
        if ($this->handler === null) {
            $this->openConnection();
            return true;
        } else {
            $this->waitForConnection();
            return false;
        }
    }

    public function disconnect(): bool
    {
        if ($this->handler === null) {
            return false;
        }

        foreach ($this->preDisconnectHooks as $hook) {
            call_user_func($hook);
        }

        // TODO: handle the case when there are some open LO handles (an LO shall be an IType registered at the connection)

        $closed = pg_close($this->handler); // NOTE: it seems correct to close a not yet established asynchronous connection
        if (!$closed) {
            throw new ConnectionException('Error closing the connection');
        }

        foreach ($this->postDisconnectHooks as $hook) {
            call_user_func($hook);
        }

        $this->handler = null;
        $this->finishedConnecting = null;
        $this->initProcedure = null;
        return true;
    }

    public function registerConnectStartHook(\Closure $closure): void
    {
        $this->connectStartHooks[] = $closure;
    }

    public function registerPreDisconnectHook(\Closure $closure): void
    {
        $this->preDisconnectHooks[] = $closure;
    }

    public function registerPostDisconnectHook(\Closure $closure): void
    {
        $this->postDisconnectHooks[] = $closure;
    }

    /**
     * @param int $connectFlags flags for {@link pg_connect()} (`PGSQL_CONNECT_FORCE_NEW` is enforced implicitly)
     */
    private function openConnection(int $connectFlags = 0)
    {
        $connStr = $this->params->buildConnectionString();
        $this->handler = pg_connect($connStr, PGSQL_CONNECT_FORCE_NEW | $connectFlags);
        $this->finishedConnecting = !($connectFlags & PGSQL_CONNECT_ASYNC);
        if ($this->handler === false || pg_connection_status($this->handler) === PGSQL_CONNECTION_BAD) {
            $this->handler = null;
            throw new ConnectionException('Error connecting to the database'); // TODO: try to squeeze the client to get some useful information; maybe trap errors issued by pg_connect()?
        }
    }

    private function waitForConnection(): void
    {
        if ($this->finishedConnecting) {
            return;
        }
        if ($this->handler === null) {
            throw new InvalidStateException('Expected to be called only on a started connection.');
        }

        $pollStatus = pg_connect_poll($this->handler);
        if ($pollStatus === PGSQL_POLLING_OK) {
            $this->finishedConnecting = true;
            if ($this->initProcedure !== null) {
                call_user_func($this->initProcedure, $this->ivoryConnection);
            }
            return;
        }

        $socket = pg_socket($this->handler);
        if (!$socket) {
            throw new ConnectionException('Error retrieving the connection socket while trying to wait for connection');
        }

        while (true) {
            switch ($pollStatus) {
                case PGSQL_POLLING_OK:
                    $this->finishedConnecting = true;
                    if ($this->initProcedure !== null) {
                        call_user_func($this->initProcedure, $this->ivoryConnection);
                    }
                    return;
                case PGSQL_POLLING_FAILED:
                    throw new ConnectionException('Failed waiting for connection');
                case PGSQL_POLLING_READING:
                    /** @noinspection PhpStatementHasEmptyBodyInspection */
                    while (!self::isStreamReadable($socket, 1)) { // TODO: make the timeout configurable
                    }
                    break;
                default:
                    usleep(10); // a micro sleep in other polling states seems better than a busy wait
                // TODO: make the micro sleep duration configurable, allowing 0 to effectively force busy wait
            }

            $pollStatus = pg_connect_poll($this->handler);
        }
    }

    private static function isStreamReadable($stream, int $sec, int $usec = 0): bool
    {
        $r = [$stream];
        $w = [];
        $ex = [];
        return (bool)stream_select($r, $w, $ex, $sec, $usec);
    }

    /**
     * Connects to the database if not already connected or if the connection has been lost.
     *
     * If an asynchronous connection has been requested, it waits until it is finished, and then returns.
     *
     * After returning, the connection is guaranteed to be established and ready for executing queries.
     *
     * @internal Only for the purpose of Ivory itself.
     *
     * @return resource handler to the connection, suitable for PHP <tt>pg_*</tt> functions
     * @throws ConnectionException on error connecting to the database
     */
    public function requireConnection()
    {
        $this->connectWait(); // it is a no-op if already connected
        assert($this->handler !== null);

        /* TODO: Currently, this method implements the Ivory feature of auto-connection when neither connect() nor
         *       connectWait() was called explicitly or when the connection got broken.
         *       That shall be configurable, though.
         */

        return $this->handler;
    }

    /**
     * @internal Only for the purpose of Ivory itself.
     *
     * @return string|null last notice received on this connection
     */
    public function getLastNotice(): ?string
    {
        return $this->lastNotice;
    }

    /**
     * @internal Only for the purpose of Ivory itself.
     *
     * @param string|null $notice last notice received on this connection
     */
    public function setLastNotice(?string $notice): void
    {
        $this->lastNotice = $notice;
    }
}
