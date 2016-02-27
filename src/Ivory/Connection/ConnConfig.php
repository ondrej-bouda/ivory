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
 * The implementation of read operations is lazy - no database query is made until actually needed.
 */
class ConnConfig implements IConnConfig
{
    const OPT_MONEY_DEC_SEP = '__' . __NAMESPACE__ . '_OPT_MONEY_DEC_SEP__';

    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
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

    public function get($propertyName)
    {
        // pg_parameter_status() might be used, only if it returned typed result
        // TODO: query
    }

    public function defined($propertyName)
    {
        // TODO
    }

    public function setForTransaction($propertyName, $value)
    {
        if (!$this->connection->inTransaction()) {
            return; // setting the option would have no effect as the implicit transaction would end immediately
        }

        // TODO
    }

    public function setForSession($propertyName, $value)
    {
        // TODO
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
            $this->get(self::OPT_DEFAULT_TRANSACTION_ISOLATION),
            $this->get(self::OPT_DEFAULT_TRANSACTION_READ_ONLY),
            $this->get(self::OPT_DEFAULT_TRANSACTION_DEFERRABLE)
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
