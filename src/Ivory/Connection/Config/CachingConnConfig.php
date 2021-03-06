<?php
/** @noinspection PhpPropertyOnlyWrittenInspection unfinished code */
declare(strict_types=1);
namespace Ivory\Connection\Config;

class CachingConnConfig extends ConnConfig implements ICachingConnConfig
{
    private $cacheEnabled = true;
    /** @var bool[] hash of names of parameters for which caching is exceptionally disabled/enabled */
    private $cacheExceptions = [];
    /** @var array map: parameter name => cached value */
    private $cachedValues = [];


    public function get(string $propertyName)
    {
        // FIXME: override - actually implement the caching; use ConnConfigValueRetriever
        return parent::get($propertyName);
    }

    public function invalidateCache($paramName = null): void
    {
        if ($paramName === null) {
            $this->cachedValues = [];
        } else {
            foreach ((array)$paramName as $pn) {
                unset($this->cachedValues[$pn]);
            }
        }
    }

    // TODO: consider combination with transactions and savepoints
    public function disableCaching($paramName = null): void
    {
        if ($paramName === null) {
            $this->cacheEnabled = false;
            $this->cacheExceptions = [];
        } elseif ($this->cacheEnabled) {
            foreach ((array)$paramName as $pn) {
                $this->cacheExceptions[$pn] = true;
            }
        } else {
            foreach ((array)$paramName as $pn) {
                unset($this->cacheExceptions[$pn]);
            }
        }

        $this->invalidateCache($paramName);
    }

    // TODO: consider combination with transactions and savepoints
    public function enableCaching($paramName = null): void
    {
        if ($paramName === null) {
            $this->cacheEnabled = true;
            $this->cacheExceptions = [];
        } elseif (!$this->cacheEnabled) {
            foreach ((array)$paramName as $pn) {
                $this->cacheExceptions[$pn] = true;
            }
        } else {
            foreach ((array)$paramName as $pn) {
                unset($this->cacheExceptions[$pn]);
            }
        }
    }
}
