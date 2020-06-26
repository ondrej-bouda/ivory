<?php
/** @noinspection PhpInappropriateInheritDocUsageInspection PhpStorm bug WI-54015 */
declare(strict_types=1);
namespace Ivory\Connection\Config;

use Ivory\Connection\ConnectionControl;
use Ivory\Connection\IObservableTransactionControl;
use Ivory\Connection\IStatementExecution;
use Ivory\Exception\UnsupportedException;
use Ivory\Value\Quantity;

/**
 * The standard implementation of runtime database configuration manager.
 *
 * {@inheritDoc}
 *
 * Besides the interface-specified methods, this implementation exposes the database configuration parameters as dynamic
 * ("overloaded") properties. The value currently in effect is always returned. For accessing properties with non-word
 * characters in their names (e.g., when using
 * {@link https://www.postgresql.org/docs/current/runtime-config-custom.html customized options}), the standard PHP
 * syntax `$config->{'some.prop'}` may be employed, or the {@link get()} method may simply be called.
 *
 * The implementation of read operations is lazy - no database query is made until actually needed. Some values are
 * gathered using the {@link pg_parameter_status()} function (these get cached internally by the PHP driver). Others are
 * directly queried and are not cached. For caching these, see the {@link CachingConnConfig} implementation.
 *
 * Data types of non-standard settings are fetched once and cached for the whole {@link ConnConfig} object lifetime
 * ({@link ConnConfig::flushCache()} may, of course, be used for flushing it in any case). Note that
 * {@link https://www.postgresql.org/docs/current/runtime-config-custom.html customized options} are always of type
 * `text`.
 *
 * As for the {@link IObservableConnConfig} implementation, any changes of configuration parameters must be done through
 * some of the methods offered by this class. Bypassing them (i.e., setting a parameter value in a raw query, or within
 * a database function) prevents the observers from being notified about the parameter value changes. In such cases, the
 * {@link ConnConfig::notifyPropertyChange()} or {@link ConnConfig::notifyPropertiesReset()} has to be called manually
 * for the new parameter value to be retrieved again.
 */
class ConnConfig implements IObservableConnConfig
{
    private $connCtl;
    private $stmtExec;
    private $txCtl;
    private $watcher;

    private $typeCache = null;
    /** @var string[]|null */
    private $effectiveSearchPathCache = null;
    /** @var IConfigObserver[][] map: parameter name or <tt>''</tt> => list of observers registered for changes of the
     *                             parameter (or any parameter for <tt>''</tt> (empty string) entry) */
    private $observers = [];


    public function __construct(
        ConnectionControl $connCtl,
        IStatementExecution $stmtExec,
        IObservableTransactionControl $txCtl
    ) {
        $this->connCtl = $connCtl;
        $this->stmtExec = $stmtExec;
        $this->txCtl = $txCtl;

        $this->watcher = new ConnConfigTransactionWatcher($this);
        $txCtl->addObserver($this->watcher);
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

    public function flushCache(): void
    {
        $this->typeCache = null;
    }

    public function get(string $propertyName)
    {
        if (self::isCustomOption($propertyName)) {
            return $this->getCustomOptionValue($propertyName);
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
            ConfigParam::SERVER_VERSION_NUM => true,
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

        if ($propertyName == ConfigParam::MONEY_DEC_SEP) {
            return $this->getMoneyDecimalSeparator();
        }

        // determine the type
        $type = null;
        // try exact match first, for performance reasons; hopefully, indexing the type map will not be needed
        if (array_key_exists($propertyName, ConfigParam::TYPEMAP)) {
            $type = ConfigParam::TYPEMAP[$propertyName];
        } else {
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
                try {
                    $type = ConfigParamType::detectType($row['vartype'], $row['setting'], $row['unit']);
                    $this->typeCache[$propertyName] = $type;
                    $this->typeCache[strtolower($propertyName)] = $type;
                    return ConfigParamType::createValue($type, $row['setting'], $row['unit']);
                } catch (UnsupportedException $e) {
                    throw new UnsupportedException("Unsupported type of configuration parameter '$propertyName'");
                }
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
        } else {
            return null;
        }
    }

    private function getCustomOptionValue(string $customOptionName): ?string
    {
        /* The custom option might not be recognized by PostgreSQL yet and an exception might be thrown, which would
         * break the current transaction (if any). Hence the savepoint.
         * Besides, custom options are always of type string, no need to even worry about the type.
         */
        $connHandler = $this->connCtl->requireConnection();
        $inTx = $this->txCtl->inTransaction();
        $savepoint = false;
        $needsRollback = false;
        $result = null;
        try {
            if ($inTx) {
                $spRes = @pg_query($connHandler, 'SAVEPOINT _ivory_customized_option');
                if ($spRes === false) {
                    throw new \RuntimeException('Error retrieving a custom option value.');
                }
                $savepoint = true;
            }
            $res = @pg_query_params($connHandler, 'SELECT pg_catalog.current_setting($1)', [$customOptionName]);
            if ($res !== false) {
                $result = pg_fetch_result($res, 0, 0);
            } else {
                $needsRollback = true;
                $result = null;
            }
        } finally {
            // anything might have been thrown, which must not break the (savepoint - release savepoint) pair
            if ($savepoint) {
                if ($needsRollback) {
                    $rbRes = @pg_query($connHandler, 'ROLLBACK TO SAVEPOINT _ivory_customized_option');
                    if ($rbRes === false) {
                        throw new \RuntimeException(
                            'Error restoring the transaction status - it stayed in the aborted state.'
                        );
                    }
                }

                $spRes = @pg_query($connHandler, 'RELEASE SAVEPOINT _ivory_customized_option');
                if ($spRes === false) {
                    throw new \RuntimeException(
                        'Error restoring the transaction status - the savepoint probably stayed defined.'
                    );
                }
            }
            return $result;
        }
    }

    /**
     * @param string $propertyName
     * @return bool whether the requested property is a custom option
     */
    private static function isCustomOption(string $propertyName): bool
    {
        // see https://www.postgresql.org/docs/11/runtime-config-custom.html
        return (strpos($propertyName, '.') !== false);
    }

    public function defined(string $propertyName): bool
    {
        return ($this->get($propertyName) !== null);
    }

    public function setForTransaction(string $propertyName, $value): void
    {
        if (!$this->txCtl->inTransaction()) {
            return; // setting the option would have no effect as the implicit transaction would end immediately
        }

        $connHandler = $this->connCtl->requireConnection();
        pg_query_params($connHandler, 'SELECT pg_catalog.set_config($1, $2, TRUE)', [$propertyName, $value]);

        $this->watcher->handleSetForTransaction($propertyName);
        $this->notifyPropertyChange($propertyName, $value);
    }

    public function setForSession(string $propertyName, $value): void
    {
        $connHandler = $this->connCtl->requireConnection();
        pg_query_params($connHandler, 'SELECT pg_catalog.set_config($1, $2, FALSE)', [$propertyName, $value]);

        $this->watcher->handleSetForSession($propertyName);
        $this->notifyPropertyChange($propertyName, $value);
    }

    public function resetAll(): void
    {
        $connHandler = $this->connCtl->requireConnection();
        pg_query($connHandler, 'RESET ALL');

        $this->watcher->handleResetAll();
        $this->notifyPropertiesReset();
    }

    public function getEffectiveSearchPath(): array
    {
        if ($this->effectiveSearchPathCache === null) {
            $this->initEffectiveSearchPathCache();
            $refresher = function () {
                $this->initEffectiveSearchPathCache();
            };
            $this->addObserver(
                new class($refresher) implements IConfigObserver
                {
                    private $refresher;

                    public function __construct($refresher)
                    {
                        $this->refresher = $refresher;
                    }

                    public function handlePropertyChange(string $propertyName, $newValue): void
                    {
                        call_user_func($this->refresher);
                    }

                    public function handlePropertiesReset(IConnConfig $connConfig): void
                    {
                        call_user_func($this->refresher);
                    }
                },
                ConfigParam::SEARCH_PATH
            );
        }

        return $this->effectiveSearchPathCache;
    }

    private function initEffectiveSearchPathCache(): void
    {
        $connHandler = $this->connCtl->requireConnection();
        $res = pg_query($connHandler, 'SELECT unnest(pg_catalog.current_schemas(TRUE))');
        if ($res !== false) {
            $this->effectiveSearchPathCache = [];
            while (($row = pg_fetch_row($res)) !== false) {
                $this->effectiveSearchPathCache[] = $row[0];
            }
        }
    }

    public function getMoneyDecimalSeparator(): string
    {
        $r = $this->stmtExec->rawQuery('SELECT 1.2::money::text');
        $v = $r->value();
        if (preg_match('~1(\D*)2~', $v, $m)) {
            return $m[1];
        } else {
            return '';
        }
    }

    public function getServerVersionNumber(): int
    {
        return $this->get(ConfigParam::SERVER_VERSION_NUM);
    }

    public function getServerMajorVersionNumber(): int
    {
        return intdiv($this->getServerVersionNumber(), 10000);
    }

    //region IObservableConnConfig

    public function addObserver(IConfigObserver $observer, $parameterName = null): void
    {
        if (is_array($parameterName)) {
            foreach ($parameterName as $pn) {
                $this->addObserverImpl($observer, $pn);
            }
        } else {
            $this->addObserverImpl($observer, (string)$parameterName); // null is represented by an empty string
        }
    }

    private function addObserverImpl(IConfigObserver $observer, string $parameterName): void
    {
        if (!isset($this->observers[$parameterName])) {
            $this->observers[$parameterName] = [];
        }

        $this->observers[$parameterName][] = $observer;
    }

    public function removeObserver(IConfigObserver $observer): void
    {
        foreach ($this->observers as $k => $obsList) {
            foreach ($obsList as $i => $obs) {
                if ($obs === $observer) {
                    unset($this->observers[$k][$i]);
                }
            }
        }
    }

    public function removeAllObservers(): void
    {
        $this->observers = [];
    }

    public function notifyPropertyChange(string $parameterName, $newValue = null): void
    {
        if ($newValue === null) {
            $newValue = $this->get($parameterName);
        }

        foreach ([$parameterName, null] as $k) {
            if (isset($this->observers[$k])) {
                foreach ($this->observers[$k] as $obs) {
                    $obs->handlePropertyChange($parameterName, $newValue);
                }
            }
        }

        if ($parameterName == ConfigParam::LC_MONETARY) {
            $this->notifyPropertyChange(ConfigParam::MONEY_DEC_SEP);
        }
    }

    public function notifyPropertiesReset(): void
    {
        $obsSet = [];
        foreach ($this->observers as $k => $obsList) {
            foreach ($obsList as $obs) {
                $obsSet[spl_object_hash($obs)] = $obs;
            }
        }
        foreach ($obsSet as $obs) {
            assert($obs instanceof IConfigObserver);
            $obs->handlePropertiesReset($this);
        }
    }

    //endregion
}
