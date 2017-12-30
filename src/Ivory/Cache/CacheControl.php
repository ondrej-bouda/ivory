<?php
declare(strict_types=1);
namespace Ivory\Cache;

use Psr\Cache\CacheItemPoolInterface;

class CacheControl implements ICacheControl
{
    /** @var CacheItemPoolInterface|null */
    private $cacheItemPool = null;
    /** @var string */
    private $prefix;
    /** @var string */
    private $postfix;

    public function __construct(
        CacheItemPoolInterface $itemPool = null,
        string $cacheKeyPrefix = '',
        string $cacheKeyPostfix = ''
    ) {
        $this->cacheItemPool = $itemPool;
        $this->prefix = ($cacheKeyPrefix === '' ? '' : self::safeCacheKey($cacheKeyPrefix));
        $this->postfix = ($cacheKeyPostfix === '' ? '' : self::safeCacheKey($cacheKeyPostfix));
    }

    /**
     * @param CacheItemPoolInterface|null $cacheItemPool cache implementation to use, or <tt>null</tt> to use the
     *                                                     default set to {@link Ivory::setDefaultCacheImpl()}
     */
    public function setCacheImpl(?CacheItemPoolInterface $cacheItemPool): void
    {
        $this->cacheItemPool = $cacheItemPool;
    }

    protected function composeCacheKey(string $objectKey): string
    {
        // object key before connection key - the connection key might contain anything out of control of Ivory
        $key = $this->prefix . self::safeCacheKey($objectKey) . $this->postfix;

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
    protected static function safeCacheKey(string $key): string
    {
        if (preg_match('~^[A-Za-z0-9_.]+$~', $key)) {
            return $key;
        } else {
            return substr(md5($key), 0, 16); // half of MD5 will probably be enough
        }
    }

    public function isCacheEnabled(): bool
    {
        return ($this->cacheItemPool !== null);
    }

    public function cachePermanently(string $cacheKey, $object): bool
    {
        if ($this->cacheItemPool === null) {
            return false;
        }

        $key = $this->composeCacheKey($cacheKey);
        /** @noinspection PhpUnhandledExceptionInspection PhpStorm bug WI-38168 */
        $item = $this->cacheItemPool->getItem($key);
        $item->set($object);

        return $this->cacheItemPool->save($item);
    }

    public function getCached(string $cacheKey)
    {
        if ($this->cacheItemPool === null) {
            return null;
        }

        $key = $this->composeCacheKey($cacheKey);
        /** @noinspection PhpUnhandledExceptionInspection PhpStorm bug WI-38168 */
        $item = $this->cacheItemPool->getItem($key);
        return $item->get();
    }

    public function flushCache(string $cacheKey): bool
    {
        if ($this->cacheItemPool === null) {
            return false;
        }

        $key = $this->composeCacheKey($cacheKey);
        /** @noinspection PhpUnhandledExceptionInspection PhpStorm bug WI-38168 */
        return $this->cacheItemPool->deleteItem($key);
    }
}
