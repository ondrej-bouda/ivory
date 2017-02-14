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
 * @see http://www.postgresql.org/docs/9.4/static/runtime-config-client.html List of Standard Client Configuration Settings
 */
interface IConnConfig
{
    const DEFAULT_VALUE = null;


    /**
     * Flushes any cached information.
     */
    function flushCache();

    /**
     * @param string $propertyName name of a configuration option
     * @return bool|float|int|Quantity|string|null current value of the requested option, or <tt>null</tt> if no such
     *                                               option has been defined yet
     */
    function get(string $propertyName);

    /**
     * @param string $propertyName name of a configuration option
     * @return bool whether the option is defined
     */
    function defined(string $propertyName): bool;

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
    function setForTransaction(string $propertyName, $value);

    /**
     * Sets the specified option to the given value with validity until the connection is closed.
     *
     * It corresponds to the SQL `SET [SESSION]` command.
     *
     * @param string $propertyName
     * @param bool|string|int|float|Quantity $value the new value, or {@link IConnConfig::DEFAULT_VALUE} to use the
     *                                                option's default
     */
    function setForSession(string $propertyName, $value);

    /**
     * Resets the values of all options to their current defaults.
     *
     * It corresponds to the SQL `RESET ALL` command. By definition, the effect is session-wide.
     */
    function resetAll();

    /**
     * @return TxConfig transaction parameters of the current transaction
     */
    function getTxConfig(): TxConfig;

    /**
     * @return TxConfig default transaction parameters for new transactions
     */
    function getDefaultTxConfig(): TxConfig;

    /**
     * @return string[] the effective search path - the explicit search path combined with the implicit schemas
     * @see https://www.postgresql.org/docs/9.6/static/runtime-config-client.html#RUNTIME-CONFIG-CLIENT-STATEMENT
     */
    function getEffectiveSearchPath();

    /**
     * @return string currently configured decimal separator used in values of type <tt>money</tt>;
     *                note this might even be an empty string if the currently configured currency has no decimal places
     */
    function getMoneyDecimalSeparator(): string;
}
