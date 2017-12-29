<?php
declare(strict_types=1);
namespace Ivory\Connection\Config;

use Ivory\Value\Quantity;

interface IConfigObserver
{
    /**
     * Handler of changes of configuration parameter values.
     *
     * @param string $propertyName property the value of which has changed
     * @param bool|float|int|Quantity|string $newValue the new value of the property
     */
    function handlePropertyChange(string $propertyName, $newValue): void;

    /**
     * Handler of event that any configuration parameter might have changed its value.
     *
     * @param IConnConfig $connConfig the connection configuration available for getting any configuration value
     */
    function handlePropertiesReset(IConnConfig $connConfig): void;
}
