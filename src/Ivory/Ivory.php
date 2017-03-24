<?php
namespace Ivory;

use Ivory\Connection\ConnectionParameters;
use Ivory\Connection\IConnection;
use Ivory\Exception\ConnectionException;
use Ivory\Exception\StatementExceptionFactory;
use Ivory\Lang\SqlPattern\SqlPatternParser;
use Ivory\Type\TypeRegister;

final class Ivory
{
    /** @var ICoreFactory */
    private static $coreFactory = null;
    /** @var TypeRegister */
    private static $typeRegister = null;
    /** @var SqlPatternParser */
    private static $sqlPatternParser = null;
    /** @var StatementExceptionFactory */
    private static $stmtExFactory = null;
    /** @var IConnection[] map: name => connection */
    private static $connections = [];
    /** @var IConnection */
    private static $defaultConn = null;


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
    public static function setCoreFactory(ICoreFactory $coreFactory)
    {
        self::$coreFactory = $coreFactory;
    }

    /**
     * @return TypeRegister the global type register, used for getting types not defined locally for a connection
     */
    public static function getTypeRegister(): TypeRegister
    {
        if (self::$typeRegister === null) {
            self::$typeRegister = self::getCoreFactory()->createTypeRegister();
        }
        return self::$typeRegister;
    }

    public static function getSqlPatternParser(): SqlPatternParser
    {
        if (self::$sqlPatternParser === null) {
            self::$sqlPatternParser = self::getCoreFactory()->createSqlPatternParser();
        }
        return self::$sqlPatternParser;
    }

    /**
     * @return StatementExceptionFactory the global statement exception factory, used for emitting the
     *                                     {@link \Ivory\Exception\StatementException}s upon statement errors;
     *                                   like the type register, an overriding factory is also defined locally on each
     *                                     connection - this factory only applies if the local one does not
     */
    public static function getStatementExceptionFactory(): StatementExceptionFactory
    {
        if (self::$stmtExFactory === null) {
            self::$stmtExFactory = self::getCoreFactory()->createStatementExceptionFactory();
        }
        return self::$stmtExFactory;
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
     *                              if not given, the database name is considered if it is given within <tt>$params</tt>
     *                                or the fallback name <tt>'conn'</tt> is used;
     *                              if not given and if the auto-generated name is already taken, it is appended with a
     *                                numeric suffix, e.g., <tt>'conn1'</tt>, <tt>'conn2'</tt>, etc.
     * @return IConnection
     * @throws ConnectionException if connection name is explicitly specified but a connection with the same name
     *                               already exists
     */
    public static function setupConnection($params, string $connName = null): IConnection
    {
        if (!$params instanceof ConnectionParameters) {
            $params = ConnectionParameters::create($params);
        }

        if ($connName === null) {
            $connName = (isset($params['dbname']) ? $params['dbname'] : 'conn');
            $safetyBreak = 10000;
            for ($i = 1; $i < $safetyBreak; $i++) {
                if (!isset(self::$connections[$connName . $i])) {
                    $connName .= $i;
                    break;
                }
            }
            if ($i >= $safetyBreak) {
                throw new \RuntimeException('Error auto-generating name for the new connection: all suffixes taken');
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
    public static function getConnection(string $connName = null): IConnection
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
            throw new \RuntimeException('Undefined connection');
        }
    }

    /**
     * @param IConnection|string $conn (name of) connection to use as the default connection
     * @throws \RuntimeException if the requested connection is not defined
     */
    public static function useConnectionAsDefault($conn)
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
     * @param IConnection|string|null $conn (name of) connection to remove from the register;
     *                                      <tt>null</tt> to remove the default connection
     * @throw \RuntimeException if the default connection is requested but no connection has been setup yet, or if the
     *                            requested connection is not defined
     */
    public static function dropConnection($conn = null)
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
            if (self::$connections[$connName] === self::$defaultConn) {
                self::$defaultConn = null;
            }
            unset(self::$connections[$connName]);
        } else {
            throw new \RuntimeException('Undefined connection');
        }
    }
}
