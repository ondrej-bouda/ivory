<?php
namespace Ivory;

use Ivory\Value\Quantity;

/**
 * Runtime database configuration manager.
 *
 * Exposes the database configuration parameters as dynamic ("overloaded") properties. The value currently in effect is
 * always returned. For accessing properties with non-word characters in their names (e.g., when using
 * {@link http://www.postgresql.org/docs/9.4/static/runtime-config-custom.html customized options}), the standard PHP
 * syntax <tt>$config->{'some.prop'}</tt> may be employed.
 *
 * Apart from reading the values, it is also possible to write new values for some settings for the current transaction,
 * session, or as the defaults for a given role, database, or the whole DBMS. In fact, changing the current transaction
 * and session parameters exclusively through this class is strongly recommended with regards to caching - see below.
 * Note that not all settings may be changed at runtime. This class does not check for whether a setting write operation
 * is possible - all operations are simply passed to the database system (and they fail unless legal). Consult the
 * {@link http://www.postgresql.org/docs/9.4/static/config-setting.html PostgreSQL manual} for more details.
 *
 * There are several types a PostgreSQL setting may take:
 * * boolean, represented by the PHP <tt>bool</tt> type,
 * * string, represented by the PHP <tt>string</tt> type,
 * * numeric, represented by the PHP <tt>int</tt> or <tt>float</tt> type,
 * * numeric with unit, represented by {@link \Ivory\Value\Quantity},
 * * enumerated, represented by the PHP <tt>string</tt> type.
 *
 * Generally, it is up to the user of this class to provide the correct type of value when writing a setting. There are
 * no checks performed by this class - everything is simply passed to the database system. Note that
 * {@link http://www.postgresql.org/docs/9.4/static/runtime-config-custom.html customized options} are treated as of
 * type <tt>string</tt> by PostgreSQL.
 *
 * The implementation of read operations is lazy - no database query is used until actually needed.
 *
 * Caching of configuration parameter values is supported, and enabled by default. That may be customized both globally
 * and for individual configuration parameters using {@link ConnConfig::disableCaching()} and
 * {@link ConnConfig::enableCaching()} methods. The cache may also be {@link ConnConfig::invalidateCache() invalidated},
 * forcing to read the fresh values once again from the database. Note that changing a setting is transactional, i.e.,
 * the original value is restored if the transaction is rolled back (either to a savepoint or as the whole transaction).
 *
 * Cached configuration parameters may be written (and subsequently read from the cache) even before the connection is
 * actually established. The values get applied once the connection is up. Note, however, that reading any cached option
 * without writing to it first, as well as writing any non-cached option, leads to an {@link InvalidStateException} if
 * no connection is alive. Also note that no values survive {@link Connection::close() closing the connection} - after
 * the connection gets closed, all cached values are invalidated and writing them leads to the same pre-caching
 * behaviour as before the first connecting. The only attribute which persists across the connection re-opening is the
 * caching settings, i.e., whether the caching is enabled (and for which configuration parameters).
 *
 * @todo provide a way to get all configuration options; maybe as Iterable?
 *
 * @see http://www.postgresql.org/docs/9.4/static/config-setting.html
 */
class ConnConfig
{
    const DEFAULT_VALUE = null;
    const OPT_MONEY_DEC_SEP = '__' . __NAMESPACE__ . '_OPT_MONEY_DEC_SEP__';

    private $connection;

    private $cacheEnabled = true;
    /** @var bool[] hash of names of parameters for which caching is exceptionally disabled/enabled */
    private $cacheExceptions = [];
    /** @var array map: parameter name => cached value */
    private $cachedValues = [];

    public function __construct(IConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param string $propertyName name of a configuration option
     * @return bool|float|int|Quantity|string|null current value of the requested option, or <tt>null</tt> if no such
     *                                               option has been defined yet
     */
    public function __get($propertyName)
    {
        // TODO
    }

    /**
     * @param string $propertyName name of a configuration option
     * @return bool whether the option is defined
     */
    public function __isset($propertyName)
    {
        // TODO
    }

    /**
     * Alias for {@link ConnConfig::setForSession()}.
     *
     * @param string $propertyName name of a configuration option
     * @param bool|string|int|float|Quantity $value the new value, or {@link ConnConfig::DEFAULT_VALUE} to use the
     *                                                option's default
     */
    public function __set($propertyName, $value)
    {
        $this->setForSession($propertyName, $value);
    }

    /**
     * Sets the specified option to the given value with validity until the end of the transaction. Once the transaction
     * ends (either committed or not), the option retains its original value (if not overwritten by a later session-wide
     * write on the same option).
     *
     * It corresponds to the SQL <tt>SET LOCAL</tt> command.
     *
     * @param string $propertyName
     * @param bool|string|int|float|Quantity $value the new value, or {@link ConnConfig::DEFAULT_VALUE} to use the
     *                                                option's default
     */
    public function setForTransaction($propertyName, $value)
    {
        if (!$this->connection->inTransaction()) {
            return; // setting the option would have no effect as the implicit transaction would end immediately
        }

        // TODO
    }

    /**
     * Sets the specified option to the given value with validity until the connection is closed.
     *
     * It corresponds to the SQL <tt>SET [SESSION]</tt> command.
     *
     * @param string $propertyName
     * @param bool|string|int|float|Quantity $value the new value, or {@link ConnConfig::DEFAULT_VALUE} to use the
     *                                                option's default
     */
    public function setForSession($propertyName, $value)
    {
        // TODO
    }


    /**
     * @return TxConfig transaction parameters of the current transaction
     */
    public function getTxConfig()
    {
        return self::createTxConfig(
            $this->transaction_isolation,
            $this->transaction_read_only,
            $this->transaction_deferrable
        );
    }

    /**
     * @return TxConfig default transaction parameters for new transactions
     */
    public function getDefaultTxConfig()
    {
        return self::createTxConfig(
            $this->default_transaction_isolation,
            $this->default_transaction_read_only,
            $this->default_transaction_deferrable
        );
    }

    private static function createTxConfig($isolationLevel, $readOnly, $deferrable)
    {
        $txConf = new TxConfig();
        $txConf->setReadOnly($readOnly);
        $txConf->setDeferrable($deferrable);

        static $isolationLevels = [
            'serializable' => TxConfig::ISOLATION_SERIALIZABLE,
            'repeatable read' => TxConfig::ISOLATION_REPEATABLE_READ,
            'read committed' => TxConfig::ISOLATION_READ_COMMITTED,
            'read uncommitted' => TxConfig::ISOLATION_READ_UNCOMMITTED,
        ];
        if (!isset($isolationLevels[$isolationLevel])) {
            throw new \InvalidArgumentException("Unrecognized transaction isolation level: '$isolationLevel'");
        }
        $txConf->setIsolationLevel($isolationLevels[$isolationLevel]);

        return $txConf;
    }

    /**
     * @return string currently configured decimal separator used in values of type <tt>money</tt>
     */
    public function getMoneyDecimalSeparator()
    {
        // TODO
    }


    /**
     * Invalidates the cache of configuration values. Next time a configuration option is read, its fresh value is
     * queried from the database.
     *
     * @param string|string[] one or more names of parameters the cached values of which to invalidate;
     *                        <tt>null</tt> invalidates everything
     */
    public function invalidateCache($paramName = null)
    {
        if ($paramName === null) {
            $this->cachedValues = [];
        }
        else {
            foreach ((array)$paramName as $pn) {
                unset($this->cachedValues[$pn]);
            }
        }
    }

    /**
     * Disables caching of values of a given configuration parameter, or of any configuration parameter.
     *
     * This is useful for parameters changed indirectly, e.g., using a custom SQL query or within a stored function.
     *
     * Use {@link enableCaching()} to enable the caching again.
     *
     * @param string|string[] $paramName one or more names of parameters to disabled caching values of;
     *                                   <tt>null</tt> disables the caching altogether
     */
    public function disableCaching($paramName = null) // TODO: consider combination with transactions and savepoints
    {
        if ($paramName === null) {
            $this->cacheEnabled = false;
            $this->cacheExceptions = [];
        }
        elseif ($this->cacheEnabled) {
            foreach ((array)$paramName as $pn) {
                $this->cacheExceptions[$pn] = true;
            }
        }
        else {
            foreach ((array)$paramName as $pn) {
                unset($this->cacheExceptions[$pn]);
            }
        }

        $this->invalidateCache($paramName);
    }

    /**
     * Enables caching of configuration parameter values again, after it has been disabled by {@link disableCaching()}.
     *
     * Note that, if caching was disabled altogether using {@link disableCaching()}, this method will only turn caching
     * on for the requested parameters. Others will remain non-cached. To turn caching generally on, use <tt>null</tt>
     * as the argument (which is the default).
     *
     * @param string|string[] $paramName
     */
    public function enableCaching($paramName = null) // TODO: consider combination with transactions and savepoints
    {
        if ($paramName === null) {
            $this->cacheEnabled = true;
            $this->cacheExceptions = [];
        }
        elseif (!$this->cacheEnabled) {
            foreach ((array)$paramName as $pn) {
                $this->cacheExceptions[$pn] = true;
            }
        }
        else {
            foreach ((array)$paramName as $pn) {
                unset($this->cacheExceptions[$pn]);
            }
        }
    }
}
