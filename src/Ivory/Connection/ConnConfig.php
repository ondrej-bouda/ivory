<?php
namespace Ivory\Connection;

use Ivory\Value\Quantity;

/**
 * The standard implementation of runtime database configuration manager.
 *
 * {@inheritdoc}
 *
 * Besides the interface-specified methods, this implementation exposes the database configuration parameters as dynamic
 * ("overloaded") properties. The value currently in effect is always returned. For accessing properties with non-word
 * characters in their names (e.g., when using
 * {@link http://www.postgresql.org/docs/9.4/static/runtime-config-custom.html customized options}), the standard PHP
 * syntax `$config->{'some.prop'}` may be employed, or the {@link get()} method may simply be called.
 *
 * The implementation of read operations is lazy - no database query is made until actually needed. Some values are
 * gathered using the {@link pg_parameter_status()} function (these get cached internally by the PHP driver). Others are
 * directly queried and are not cached. For caching these, see the {@link CachingConnConfig} implementation.
 *
 * Data types of non-standard settings are fetched once and cached for the whole {@link ConnConfig} object lifetime
 * ({@link ConnConfig::flushCache()} may, of course, be used for flushing it in any case). Note that
 * {@link http://www.postgresql.org/docs/9.4/static/runtime-config-custom.html customized options} are always of type
 * `text`.
 */
class ConnConfig implements IConnConfig
{
    const OPT_MONEY_DEC_SEP = '__' . __NAMESPACE__ . '_OPT_MONEY_DEC_SEP__';

    private $connCtl;
    private $txCtl;

    private $typeCache = null;


    public function __construct(ConnectionControl $connCtl, ITransactionControl $txCtl)
    {
        $this->connCtl = $connCtl;
        $this->txCtl = $txCtl;
    }


    /**
     * Alias for {@link ConnConfig::get().
     *
     * @param string $propertyName name of a configuration option
     * @return bool|float|int|Quantity|string|null current value of the requested option, or <tt>null</tt> if no such
     *                                               option has been defined yet
     */
    public function __get($propertyName)
    {
        return $this->get($propertyName);
    }

    /**
     * Alias for {@link ConnConfig::defined()}.
     *
     * @param string $propertyName name of a configuration option
     * @return bool whether the option is defined
     */
    public function __isset($propertyName)
    {
        return $this->defined($propertyName);
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

    public function flushCache()
    {
        $this->typeCache = null;
    }

    public function get($propertyName)
    {
        if (self::isCustomOption($propertyName)) {
            /* Custom options are always of type string, no need to even worry about the type.
             * The custom option might not be recognized by PostgreSQL yet and an exception might be thrown, which would
             * break the current transaction (if any). Hence the safe-point.
             */
            $sp = ($this->txCtl->inTransaction() ? 'customized_option' : null);
            if ($sp) {
                $this->txCtl->savepoint($sp);
            }
            $connHandler = $this->connCtl->requireConnection();
            $res = @pg_query_params($connHandler, 'SELECT pg_catalog.current_setting($1)', [$propertyName]);
            if ($sp) {
                if ($res === false) {
                    $this->txCtl->rollbackToSavepoint($sp);
                }
                $this->txCtl->releaseSavepoint($sp);
            }
            if ($res !== false) {
                return pg_fetch_result($res, 0, 0);
            }
            else {
                return null;
            }
        }

        static $pgParStatusRecognized = [
            ConfigParam::IS_SUPERUSER => true,
            ConfigParam::SESSION_AUTHORIZATION => true,
            ConfigParam::APPLICATION_NAME => true,
            ConfigParam::DATE_STYLE => true,
            ConfigParam::INTERVAL_STYLE => true,
            ConfigParam::TIME_ZONE => true,
            ConfigParam::CLIENT_ENCODING => true,
            ConfigParam::STANDARD_CONFORMING_STRINGS => true,
            ConfigParam::INTEGER_DATETIMES => true,
            ConfigParam::SERVER_ENCODING => true,
            ConfigParam::SERVER_VERSION => true,
        ];
        if (isset($pgParStatusRecognized[$propertyName])) {
            $connHandler = $this->connCtl->requireConnection();
            $val = pg_parameter_status($connHandler, $propertyName);
            if ($val !== false) {
                $type = ConfigParam::TYPEMAP[$propertyName];
                assert($type !== null);
                return ConfigParamType::createValue($type, $val);
            }
        }

        // determine the type
        $type = null;
        // try exact match first, for performance reasons; hopefully, indexing the type map will not be needed
        if (array_key_exists($propertyName, ConfigParam::TYPEMAP)) {
            $type = ConfigParam::TYPEMAP[$propertyName];
        }
        else {
            // okay, try to search for case-insensitive matches
            if ($this->typeCache === null) {
                $this->typeCache = array_change_key_case(ConfigParam::TYPEMAP, CASE_LOWER);
            }
            $lowerPropertyName = strtolower($propertyName);
            if (isset($this->typeCache[$lowerPropertyName])) {
                $type = $this->typeCache[$lowerPropertyName];
            }
        }

        $connHandler = $this->connCtl->requireConnection();

        if ($type === null) { // type unknown, try to look in the catalog for server configuration
            $query = 'SELECT setting, vartype, unit FROM pg_catalog.pg_settings WHERE name ILIKE $1';
            $res = pg_query_params($connHandler, $query, [$propertyName]);
            if ($res !== false || pg_num_rows($res) > 0) {
                $row = pg_fetch_assoc($res);
                $type = ConfigParamType::detectType($row['vartype'], $row['setting'], $row['unit']);
                $this->typeCache[$propertyName] = $type;
                $this->typeCache[strtolower($propertyName)] = $type;
                return ConfigParamType::createValue($type, $row['setting'], $row['unit']);
            }
        }

        if ($type === null) {
            /* As the last resort, treat the value as a string. Note that the pg_settings view might not contain all,
             * e.g., "session_authorization" - which is recognized by pg_parameter_status() and is probably just an
             * exception which gets created automatically for the connection, but who knows...
             */
            $type = ConfigParamType::STRING;
        }

        $res = pg_query_params($connHandler, 'SELECT pg_catalog.current_setting($1)', [$propertyName]);
        if ($res !== false) {
            $val = pg_fetch_result($res, 0, 0);
            return ConfigParamType::createValue($type, $val);
        }
        else {
            return null;
        }
    }

    /**
     * @param string $propertyName
     * @return bool whether the requested property is a custom option
     */
    private static function isCustomOption($propertyName)
    {
        // see http://www.postgresql.org/docs/9.4/static/runtime-config-custom.html
        return (strpos($propertyName, '.') !== false);
    }

    public function defined($propertyName)
    {
        return ($this->get($propertyName) !== null);
    }

    public function setForTransaction($propertyName, $value)
    {
        if (!$this->txCtl->inTransaction()) {
            return; // setting the option would have no effect as the implicit transaction would end immediately
        }

        $connHandler = $this->connCtl->requireConnection();
        pg_query_params($connHandler, 'SELECT pg_catalog.set_config($1, $2, TRUE)', [$propertyName, $value]);
    }

    public function setForSession($propertyName, $value)
    {
        $connHandler = $this->connCtl->requireConnection();
        pg_query_params($connHandler, 'SELECT pg_catalog.set_config($1, $2, FALSE)', [$propertyName, $value]);
    }

    public function getTxConfig()
    {
        return self::createTxConfig(
            $this->transaction_isolation,
            $this->transaction_read_only,
            $this->transaction_deferrable
        );
    }

    public function getDefaultTxConfig()
    {
        return self::createTxConfig(
            $this->get(ConfigParam::DEFAULT_TRANSACTION_ISOLATION),
            $this->get(ConfigParam::DEFAULT_TRANSACTION_READ_ONLY),
            $this->get(ConfigParam::DEFAULT_TRANSACTION_DEFERRABLE)
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

    public function getMoneyDecimalSeparator()
    {
        // TODO
    }
}
