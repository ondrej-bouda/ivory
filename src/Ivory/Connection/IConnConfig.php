<?php
namespace Ivory\Connection;

use Ivory\Value\Quantity;

/**
 * Manager of runtime database configuration settings.
 *
 * Offers an interface to access current values of the configuration parameters.
 *
 * Names of the PostgreSQL standard configuration settings are included in this interface as `OPT_*` constants.
 *
 * Note that PostgreSQL treats names of all configuration settings as case-insensitive.
 *
 * Apart from reading the values, it is also possible to write new values for some settings for the current transaction
 * or session. Note that not all settings may be changed at runtime. This class does not check for whether a setting
 * write operation is possible - all operations are simply passed to the database system (and they fail unless legal).
 * Consult the {@link http://www.postgresql.org/docs/9.4/static/config-setting.html PostgreSQL manual} for more details.
 *
 * There are several types a PostgreSQL setting may take:
 * * boolean, represented by the PHP `bool` type,
 * * string, represented by the PHP `string` type,
 * * numeric, represented by the PHP `int` or `float` type,
 * * numeric with unit, represented by {@link \Ivory\Value\Quantity},
 * * enumerated, represented by the PHP `string` type.
 *
 * Generally, it is up to the user of this class to provide the correct type of value when writing a setting. There are
 * no checks performed by this class - everything is simply passed to the database system, as is. Note that
 * {@link http://www.postgresql.org/docs/9.4/static/runtime-config-custom.html customized options} are treated as of
 * type `string` by PostgreSQL.
 *
 * @todo provide a way to get all configuration options; maybe as Iterable?
 * @see http://www.postgresql.org/docs/9.4/static/config-setting.html Description of Configuration Settings
 * @see http://www.postgresql.org/docs/9.4/static/runtime-config-client.html List of Standard Configuration Settings
 */
interface IConnConfig
{
    const DEFAULT_VALUE = null;

    const OPT_SEARCH_PATH = 'search_path';
    const OPT_ROW_SECURITY = 'row_security';
    const OPT_DEFAULT_TABLESPACE = 'default_tablespace';
    const OPT_TEMP_TABLESPACES = 'temp_tablespaces';
    const OPT_CHECK_FUNCTION_BODIES = 'check_function_bodies';
    const OPT_DEFAULT_TRANSACTION_ISOLATION = 'default_transaction_isolation';
    const OPT_DEFAULT_TRANSACTION_READ_ONLY = 'default_transaction_read_only';
    const OPT_DEFAULT_TRANSACTION_DEFERRABLE = 'default_transaction_deferrable';
    const OPT_SESSION_REPLICATION_ROLE = 'session_replication_role';
    const OPT_STATEMENT_TIMEOUT = 'statement_timeout';
    const OPT_LOCK_TIMEOUT = 'lock_timeout';
    const OPT_VACUUM_FREEZE_TABLE_AGE = 'vacuum_freeze_table_age';
    const OPT_VACUUM_FREEZE_MIN_AGE = 'vacuum_freeze_min_age';
    const OPT_VACUUM_MULTIXACT_FREEZE_TABLE_AGE = 'vacuum_multixact_freeze_table_age';
    const OPT_VACUUM_MULTIXACT_FREEZE_MIN_AGE = 'vacuum_multixact_freeze_min_age';
    const OPT_BYTEA_OUTPUT = 'bytea_output';
    const OPT_XMLBINARY = 'xmlbinary';
    const OPT_XMLOPTION = 'xmloption';
    const OPT_GIN_PENDING_LIST_LIMIT = 'gin_pending_list_limit';
    const OPT_DATE_STYLE = 'DateStyle';
    const OPT_INTERVAL_STYLE = 'IntervalStyle';
    const OPT_TIME_ZONE = 'TimeZone';
    const OPT_TIMEZONE_ABBREVIATIONS = 'timezone_abbreviations';
    const OPT_EXTRA_FLOAT_DIGITS = 'extra_float_digits';
    const OPT_CLIENT_ENCODING = 'client_encoding';
    const OPT_LC_MESSAGES = 'lc_messages';
    const OPT_LC_MONETARY = 'lc_monetary';
    const OPT_LC_NUMERIC = 'lc_numeric';
    const OPT_LC_TIME = 'lc_time';
    const OPT_DEFAULT_TEXT_SEARCH_CONFIG = 'default_text_search_config';
    const OPT_LOCAL_PRELOAD_LIBRARIES = 'local_preload_libraries';
    const OPT_SESSION_PRELOAD_LIBRARIES = 'session_preload_libraries';
    const OPT_SHARED_PRELOAD_LIBRARIES = 'shared_preload_libraries';
    const OPT_DYNAMIC_LIBRARY_PATH = 'dynamic_library_path';
    const OPT_GIN_FUZZY_SEARCH_LIMIT = 'gin_fuzzy_search_limit';


    /**
     * @param string $propertyName name of a configuration option
     * @return bool|float|int|Quantity|string|null current value of the requested option, or <tt>null</tt> if no such
     *                                               option has been defined yet
     */
    function get($propertyName);

    /**
     * @param string $propertyName name of a configuration option
     * @return bool whether the option is defined
     */
    function defined($propertyName);

    /**
     * Sets the specified option to the given value with validity until the end of the transaction. Once the transaction
     * ends (either committed or not), the option retains its original value (if not overwritten by a later session-wide
     * write on the same option).
     *
     * It corresponds to the SQL `SET LOCAL` command.
     *
     * @param string $propertyName
     * @param bool|string|int|float|Quantity $value the new value, or {@link IConnConfig::DEFAULT_VALUE} to use the
     *                                                option's default
     */
    function setForTransaction($propertyName, $value);

    /**
     * Sets the specified option to the given value with validity until the connection is closed.
     *
     * It corresponds to the SQL `SET [SESSION]` command.
     *
     * @param string $propertyName
     * @param bool|string|int|float|Quantity $value the new value, or {@link IConnConfig::DEFAULT_VALUE} to use the
     *                                                option's default
     */
    function setForSession($propertyName, $value);

    /**
     * @return TxConfig transaction parameters of the current transaction
     */
    function getTxConfig();

    /**
     * @return TxConfig default transaction parameters for new transactions
     */
    function getDefaultTxConfig();

    /**
     * @return string currently configured decimal separator used in values of type <tt>money</tt>
     */
    function getMoneyDecimalSeparator();
}
