<?php
declare(strict_types=1);
namespace Ivory;

use Ivory\Cache\ICacheControl;
use Ivory\Connection\ConnectionParameters;
use Ivory\Connection\IConnection;
use Ivory\Exception\ConnectionException;
use Ivory\Exception\StatementExceptionFactory;
use Ivory\Lang\SqlPattern\ISqlPatternParser;
use Ivory\Type\TypeRegister;
use Ivory\Value\Alg\IValueComparator;
use Psr\Cache\CacheItemPoolInterface;

final class Ivory
{
    const VERSION = '0.1.0a1';
    
    /** @var ICoreFactory */
    private static $coreFactory = null;
    /** @var TypeRegister */
    private static $typeRegister = null;
    /** @var ISqlPatternParser */
    private static $sqlPatternParser = null;
    /** @var StatementExceptionFactory */
    private static $stmtExFactory = null;
    /** @var IConnection[] map: name => connection */
    private static $connections = [];
    /** @var IConnection|null */
    private static $defaultConn = null;
    /** @var CacheItemPoolInterface|null */
    private static $defaultCacheImpl = null;
    /** @var ICacheControl|null */
    private static $globalCacheControl = null;
    /** @var IValueComparator */
    private static $defaultValueComparator = null;


    private function __construct()
    {
        // the class is static - no actual instance is to be used
    }

    public static function getCoreFactory(): ICoreFactory
    {
        if (self::$coreFactory === null) {
            self::$coreFactory = new StdCoreFactory();
        }
        return self::$coreFactory;
    }

    /**
     * Provides Ivory with a factory for core objects.
     *
     * Note that {@link Ivory} caches the objects so setting another core factory is only effective at the very
     * beginning of working with Ivory.
     *
     * @param ICoreFactory $coreFactory
     */
    public static function setCoreFactory(ICoreFactory $coreFactory): void
    {
        self::$coreFactory = $coreFactory;
    }

    /**
     * Returns the global type register, used for getting types not defined locally for a connection.
     */
    public static function getTypeRegister(): TypeRegister
    {
        if (self::$typeRegister === null) {
            self::$typeRegister = self::getCoreFactory()->createGlobalTypeRegister();
        }
        return self::$typeRegister;
    }

    public static function getSqlPatternParser(): ISqlPatternParser
    {
        if (self::$sqlPatternParser === null) {
            $cacheControl = self::getGlobalCacheControl();
            self::$sqlPatternParser = self::getCoreFactory()->createSqlPatternParser($cacheControl);
        }
        return self::$sqlPatternParser;
    }

    /**
     * Returns the global statement exception factory.
     *
     * An exception factory is used for emitting {@link \Ivory\Exception\StatementException}s upon statement errors.
     * Like the type register, an overriding exception factory is also defined locally on each connection - this factory
     * only applies if the local one does not.
     */
    public static function getStatementExceptionFactory(): StatementExceptionFactory
    {
        if (self::$stmtExFactory === null) {
            self::$stmtExFactory = self::getCoreFactory()->createStatementExceptionFactory();
        }
        return self::$stmtExFactory;
    }

    /**
     * @return ICacheControl cache control used globally, independent of any specific connection
     */
    public static function getGlobalCacheControl(): ICacheControl
    {
        if (self::$globalCacheControl === null) {
            self::$globalCacheControl = self::getCoreFactory()->createCacheControl();
        }
        return self::$globalCacheControl;
    }

    /**
     * @return CacheItemPoolInterface|null the cache implementation to use for connections which do not set their own
     *                                       cache, or <tt>null</tt> if no default cache implementation is set up
     */
    public static function getDefaultCacheImpl(): ?CacheItemPoolInterface
    {
        return self::$defaultCacheImpl;
    }

    /**
     * @param CacheItemPoolInterface|null $cacheItemPool the cache implementation to use for connections which do not
     *                                                     set their own cache, or <tt>null</tt> for no default cache
     */
    public static function setDefaultCacheImpl(?CacheItemPoolInterface $cacheItemPool): void
    {
        self::$defaultCacheImpl = $cacheItemPool;
    }

    /**
     * Sets up a new database connection.
     *
     * If this is the first connection to set up, it is automatically set as the default connection.
     *
     * The connection with the database is not immediately established - just a {@link Connection} object is initialized
     * and returned, it connects to the database on demand.
     *
     * @param mixed $params the parameters for creating the connection;
     *                      anything accepted by {@link ConnectionParameters::create()} - one of the following options:
     *                        <ul>
     *                          <li>a {@link ConnectionParameters} object
     *                          <li>a URI, e.g., <tt>"postgresql://usr@localhost:5433/db?connect_timeout=10"</tt>
     *                          <li>a PostgreSQL connection string, e.g.,
     *                              <tt>"host=localhost port=5432 dbname=mydb connect_timeout=10"</tt>
     *                          <li>a map of connection parameter keywords to values, e.g., <tt>['host' => '/tmp']</tt>
     *                        </ul>
     * @param string|null $connName name for the connection;
     *                              if not given, the database name is considered if it is given within
     *                                <tt>$params</tt>, or the fallback name <tt>'conn'</tt> is used;
     *                              if not given and if the auto-generated name is already taken, it is appended with a
     *                                numeric suffix, e.g., <tt>'conn1'</tt>, <tt>'conn2'</tt>, etc.
     * @return IConnection
     * @throws ConnectionException if connection name is explicitly specified but a connection with the same name
     *                               already exists
     */
    public static function setupNewConnection($params, ?string $connName = null): IConnection
    {
        if (!$params instanceof ConnectionParameters) {
            $params = ConnectionParameters::create($params);
        }

        if ($connName === null) {
            $connName = ($params->getDbName() ?? 'conn');

            if (isset(self::$connections[$connName])) {
                $safetyBreak = 10000;
                for ($i = 1; $i < $safetyBreak; $i++) {
                    if (!isset(self::$connections[$connName . $i])) {
                        $connName .= $i;
                        break;
                    }
                }
                if ($i >= $safetyBreak) {
                    throw new \RuntimeException(
                        'Error auto-generating name for the new connection: all suffixes taken'
                    );
                }
            }
        }

        $conn = self::getCoreFactory()->createConnection($connName, $params);

        if (!self::$connections) {
            self::useConnectionAsDefault($conn);
        }

        self::$connections[$connName] = $conn;

        return $conn;
    }

    /**
     * @param string|null $connName name of the connection to get; <tt>null</tt> to get the default connection
     * @return IConnection
     * @throws \RuntimeException if the default connection is requested but no connection has been setup yet, or if the
     *                             requested connection is not defined
     */
    public static function getConnection(?string $connName = null): IConnection
    {
        if ($connName === null) {
            if (self::$defaultConn) {
                return self::$defaultConn;
            } else {
                throw new \RuntimeException('No connection has been setup');
            }
        }

        if (isset(self::$connections[$connName])) {
            return self::$connections[$connName];
        } else {
            throw new \RuntimeException("Undefined connection: '$connName'");
        }
    }

    /**
     * @param IConnection|string $conn (name of) connection to use as the default connection
     * @throws \RuntimeException if the requested connection is not defined
     */
    public static function useConnectionAsDefault($conn): void
    {
        if (is_string($conn)) {
            if (isset(self::$connections[$conn])) {
                $conn = self::$connections[$conn];
            } else {
                throw new \RuntimeException('Undefined connection');
            }
        } elseif (!$conn instanceof IConnection) {
            throw new \InvalidArgumentException('conn');
        }

        self::$defaultConn = $conn;
    }

    /**
     * Removes a connection from the register so that it no longer occupies memory.
     *
     * Unless instructed otherwise by the `$disconnect` argument, the connection gets closed if still connected.
     *
     * @param IConnection|string|null $conn (name of) connection to remove from the register;
     *                                      <tt>null</tt> to remove the default connection
     * @param bool $disconnect if <tt>true</tt>, the connection to database will be closed
     * @throw \RuntimeException if the default connection is requested but no connection has been setup yet, or if the
     *                            requested connection is not defined
     */
    public static function dropConnection($conn = null, bool $disconnect = true): void
    {
        if ($conn instanceof IConnection) {
            $connName = $conn->getName();
        } elseif ($conn === null) {
            if (self::$defaultConn) {
                $connName = self::$defaultConn->getName();
            } else {
                throw new \RuntimeException('No connection has been setup');
            }
        } else {
            $connName = $conn;
        }

        if (isset(self::$connections[$connName])) {
            $droppedConn = self::$connections[$connName];
            unset(self::$connections[$connName]);
            if ($droppedConn === self::$defaultConn) {
                self::$defaultConn = null;
            }
            if ($disconnect) {
                $droppedConn->disconnect();
            }
        } else {
            throw new \RuntimeException('Undefined connection');
        }
    }

    /**
     * @return IValueComparator value comparator to use as the default one
     */
    public static function getDefaultValueComparator(): IValueComparator
    {
        if (self::$defaultValueComparator === null) {
            self::$defaultValueComparator = self::getCoreFactory()->createDefaultValueComparator();
        }
        return self::$defaultValueComparator;
    }

    /**
     * Discards the default value comparator, cached by {@link getDefaultValueComparator()}.
     */
    public static function flushDefaultValueComparator(): void
    {
        self::$defaultValueComparator = null;
    }
}
