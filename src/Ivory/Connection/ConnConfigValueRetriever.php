<?php
namespace Ivory\Connection;

class ConnConfigValueRetriever implements IConfigObserver
{
    private $connConfig;
    private $propName;
    private $valueProcessor;

    private $isCached = false;
    private $cachedValue;
    private $observingConfig = false;
    private $warnedOnPerformancePenalty = false;

    /**
     * @param IConnConfig $connConfig connection configuration
     * @param string $propertyName name of configuration property to retrieve values of
     * @param callable|null $valueProcessor a function applied to the retrieved value before it is returned;
     *                                      given a single argument: the value of the property as retrieved from the
     *                                        <tt>$connConfig</tt>;
     *                                      expected to return the value which should be returned by {@link getValue()}
     */
    public function __construct(IConnConfig $connConfig, $propertyName, callable $valueProcessor = null)
    {
        $this->connConfig = $connConfig;
        $this->propName = $propertyName;
        $this->valueProcessor = $valueProcessor;

        if ($connConfig instanceof IObservableConnConfig) {
            $connConfig->addObserver($this, $this->propName);
            $this->observingConfig = true;
        }
    }

    public function getValue()
    {
        if ($this->isCached) {
            return $this->cachedValue;
        }

        $value = $this->connConfig->get($this->propName);
        if ($this->valueProcessor !== null) {
            $value = call_user_func($this->valueProcessor, $value);
        }

        if ($this->observingConfig) {
            $this->cachedValue = $value;
            $this->isCached = true;
        } else {
            if (!$this->warnedOnPerformancePenalty) {
                trigger_error(
                    sprintf('Performance penalty: must retrieve the "%s" config value for every single item. Use an %s',
                        $this->propName, IObservableConnConfig::class
                    ),
                    E_USER_NOTICE
                );
                $this->warnedOnPerformancePenalty = true;
            }
        }

        return $value;
    }

    public function invalidateCache()
    {
        $this->isCached = false;
        $this->cachedValue = null;
    }


    //region IObservableConnConfig

    public function handlePropertyChange($propertyName, $newValue)
    {
        if ($this->valueProcessor !== null) {
            $newValue = call_user_func($this->valueProcessor, $newValue);
        }
        $this->cachedValue = $newValue;
        $this->isCached = true;
    }

    public function handlePropertiesReset(IConnConfig $connConfig)
    {
        $this->invalidateCache();
    }

    //endregion
}
