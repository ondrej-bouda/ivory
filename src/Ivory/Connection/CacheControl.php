<?php
namespace Ivory\Connection;

use Ivory\Ivory;
use Psr\Cache\CacheItemPoolInterface;

class CacheControl implements ICacheControl
{
    /** @var CacheItemPoolInterface|null */
    private $cacheItemPool = null;
    /** @var string */
    private $ivoryCacheKey;
    /** @var string */
    private $connectionCacheKey;

    public function __construct(ConnectionParameters $connectionParameters)
    {
        $this->ivoryCacheKey = self::safeCacheKey('Ivory' . Ivory::VERSION . '.');
        $this->connectionCacheKey = self::safeCacheKey(sprintf(
            '.%s.%d.%s',
            $connectionParameters->getHost(),
            $connectionParameters->getPort(),
            $connectionParameters->getDbName()
        ));
    }

    /**
     * @param CacheItemPoolInterface|null $cacheItemPool cache implementation to use, or <tt>null</tt> to use the
     *                                                     default set to {@link Ivory::setDefaultCacheImpl()}
     */
    public function setCacheImpl($cacheItemPool)
    {
        $this->cacheItemPool = $cacheItemPool;
    }

    private function composeCacheKey(string $objectKey)
    {
        // object key before connection key - the connection key might contain anything out of control of Ivory
        $key = $this->ivoryCacheKey . self::safeCacheKey($objectKey) . $this->connectionCacheKey;

        // PSR-6 only guarantees acceptance of keys length at most 64 characters - shorten the key if longer
        if (strlen($key) > 64) { // strlen() is safe here since the key will only consist of one-byte UTF-8 characters
            $key = substr($key, 0, 32) . md5($key);
        }

        return $key;
    }

    /**
     * Returns a string only consisting of characters safe to be used as a PSR-6 cache key.
     *
     * @see http://www.php-fig.org/psr/psr-6/
     *
     * @param string $key
     * @return string
     */
    private static function safeCacheKey(string $key): string
    {
        if (preg_match('~^[A-Za-z0-9_.]+$~', $key)) {
            return $key;
        } else {
            return substr(md5($key), 0, 16); // half of MD5 will probably be enough
        }
    }

    public function isCacheEnabled(): bool
    {
        return (($this->cacheItemPool ?? Ivory::getDefaultCacheImpl()) !== null);
    }

    public function cachePermanently(string $cacheKey, $object): bool
    {
        $cachePool = ($this->cacheItemPool ?? Ivory::getDefaultCacheImpl());
        if ($cachePool === null) {
            return false;
        }

        $key = $this->composeCacheKey($cacheKey);
        $item = $cachePool->getItem($key);
        $item->set($object);

        return $cachePool->save($item);
    }

    public function getCached(string $cacheKey)
    {
        $cachePool = ($this->cacheItemPool ?? Ivory::getDefaultCacheImpl());
        if ($cachePool === null) {
            return null;
        }

        $key = $this->composeCacheKey($cacheKey);
        $item = $cachePool->getItem($key);
        return $item->get();
    }

    public function flushCache(string $cacheKey): bool
    {
        $cachePool = ($this->cacheItemPool ?? Ivory::getDefaultCacheImpl());
        if ($cachePool === null) {
            return false;
        }

        $key = $this->composeCacheKey($cacheKey);
        return $cachePool->deleteItem($key);
    }
}
